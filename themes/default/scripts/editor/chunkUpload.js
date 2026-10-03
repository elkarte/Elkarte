/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 Beta 1
 */

/**
 * This file contains JavaScript associated with the chunked upload functionality
 */
class chunkUpload
{
	constructor (params)
	{
		this.url = params.url;
		this.form = params.form;
		this.chunkSize = params.chunkSize || 1000000; // 1MB default
		this.maxChunks = params.maxChunks || 1000;
		this.concurrency = params.concurrency || 4; // 4 concurrent (chunks) default
		this.retries = params.retries || 4;
		this.delayBeforeRetry = params.delayBeforeRetry || 5;
		this.signal = params.signal;

		// Ensure the params are valid
		this._validateParams();

		// Setup for this file
		this._init();
		this._eventEmitter = new ChunkEventEmitter();

		// Guard: abort before sending anything if the file requires more chunks than the server allows
		if (this.totalChunks > this.maxChunks)
		{
			setTimeout(() => this._eventEmitter.emit('error', 'chunk_quota'), 0);
			return;
		}

		// Send the fragments
		this._sendChunks();
	}

	/**
	 * Subscribe to an event, there are currently 4 events emitted
	 * - fileRetry
	 * - progress
	 * - error
	 * - complete
	 */
	on(eType, fn)
	{
		this._eventEmitter.on(eType, fn);

		return this;
	}

	/**
	 * Sends a finalized request to the server.
	 * It appends necessary data to FormData and sends a POST request to the specified URL.
	 * Throws an error in case of a network error.
	 */
	finalize()
	{
		const combineChunkForm = new FormData();

		combineChunkForm.append('elkuuid', String(this.uuid));
		combineChunkForm.append('elkchunkindex', String(this.totalChunks));
		combineChunkForm.append('elktotalchunkcount', String(this.totalChunks));
		combineChunkForm.append('filename', this.file.name);
		combineChunkForm.append('filesize', this.file.size);
		combineChunkForm.append('filetype', this.file.type);
		combineChunkForm.append(elk_session_var, elk_session_id);
		combineChunkForm.append('async', 'complete');

		fetch(this.url, {
			method: 'POST',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
			},
			body: combineChunkForm,
			cache: 'no-store'
		})
			.then(response => {
				if (!response.ok)
				{
					let error = new Error('Network error');
					error.cause = response;
					throw error;
				}

				return response.json();
			})
			.then(response => {
				this._eventEmitter.emit('done',  response);
			})
			.catch(error => {
				this._eventEmitter.emit('error', error.message);

				if ('console' in window && console.error)
				{
					console.error('Error : ', error);
				}
			})
			.finally(() => {
				this._eventEmitter.emit('always', {});
			});
	}

	/**
	 * Initialize the object's properties for chunking this file
	 *
	 * @private
	 */
	_init ()
	{
		this.file = this.form.get('attachment[]');
		this.totalChunks = this._getTotalChunks();
		this.uuid = this._getUniqueId();
		this.concurrency = Math.min(this.concurrency, this.totalChunks);
		this.chunkQueue = Array.from({length: this.totalChunks}, (_, i) => i);
		this.activeWorkers = 0;
		this.completedChunks = new Set();
		this.retriesMap = new Map();
		this.isAborted = false;
		this.hasError = false;
	}

	/**
	 * Private method to validate parameters before executing the main logic.
	 *
	 * @private
	 *
	 * @throws {TypeError} Throws an error if the "url" parameter is not defined.
	 * @throws {TypeError} Throws an error if the "form" parameter is not an instance of FormData.
	 * @throws {TypeError} Throws an error if the "attachment[]" parameter in the FormData is not an instance of File.
	 */
	_validateParams ()
	{
		if (!this.url || !this.url.length)
		{
			throw new TypeError('url must be defined');
		}

		if (!(this.form instanceof FormData))
		{
			throw new TypeError('Form must be a FormData object');
		}

		if (!(this.form.get('attachment[]') instanceof File))
		{
			throw new TypeError('attachment in FormData must be a File object');
		}
	}

	/**
	 * Calculates the total number of chunks based on the file size and chunk size.
	 *
	 * @returns {number} The total number of chunks.
	 * @private
	 */
	_getTotalChunks ()
	{
		return Math.ceil(this.file.size / (this.chunkSize));
	}

	/**
	 * Generates a unique ID using a combination of random numbers and the current timestamp.
	 *
	 * @returns {number} The unique ID.
	 * @private
	 */
	_getUniqueId ()
	{
		return Math.floor(Math.random() * 123456789) + Date.now() + this.file.size;
	}

	/**
	 * Retrieves the slice of data for a specific chunk index.
	 *
	 * @param {number} chunkIndex The index of the chunk to extract.
	 * @returns {Blob} The sliced blob segment.
	 * @private
	 */
	_getChunkBlob (chunkIndex)
	{
		const length = this.totalChunks === 1 ? this.file.size : this.chunkSize;
		const start = length * chunkIndex;

		return this.file.slice(start, start + length);
	}

	/**
	 * Sends a specific chunk of data to the server.
	 *
	 * @param {number} chunkIndex The index of the chunk being uploaded.
	 * @private
	 * @returns {void}
	 */
	_uploadChunk (chunkIndex)
	{
		this.activeWorkers++;
		const chunkBlob = this._getChunkBlob(chunkIndex);
		const chunkForm = new FormData();

		// Load the form with useful data
		chunkForm.append('elkchunkindex', String(chunkIndex));
		chunkForm.append('elktotalchunkcount', String(this.totalChunks));
		chunkForm.append('elkuuid', String(this.uuid));
		chunkForm.append('filename', this.file.name);
		chunkForm.append('filesize', String(chunkBlob.size));
		chunkForm.append('filetype', this.file.type);
		chunkForm.append('attachment[]', chunkBlob);
		chunkForm.append(elk_session_var, elk_session_id);

		// Provide a way for the user to abort the upload
		let signal = this.signal;

		fetch(this.url, {
			signal,
			method: 'POST',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
			},
			body: chunkForm,
			cache: 'no-store'
		})
			.then(response => {
				if (!response.ok)
				{
					let error = new Error('Network error');
					error.cause = response;
					throw error;
				}

				return response.json();
			})
			.then(response => {
				this.activeWorkers--;

				if (this.hasError || this.isAborted)
				{
					return;
				}

				if (response.result === true)
				{
					this.completedChunks.add(chunkIndex);

					const percentProgress = Math.round((100 / this.totalChunks) * this.completedChunks.size);
					this._eventEmitter.emit('progress', percentProgress);

					if (this.completedChunks.size === this.totalChunks)
					{
						this._eventEmitter.emit('complete', response);
					}
					else
					{
						this._sendChunks();
					}
				}
				else
				{
					let error = new Error('Error: ' + response.error);
					error.cause = {'status': 200};
					throw error;
				}
			})
			.catch((response) => {
				this.activeWorkers--;

				if (this.hasError || this.isAborted)
				{
					return;
				}

				if (response.name === 'AbortError')
				{
					this.isAborted = true;
					this._eventEmitter.emit('error', 'abort');
				}
				else if (response.cause && [408, 502, 503, 504].includes(response.cause.status))
				{
					this._manageRetries(chunkIndex);
				}
				else
				{
					this.hasError = true;
					this._eventEmitter.emit('error', response.message);
				}
			});
	}

	/**
	 * Manages retries for uploading a specific chunk of a file.
	 *
	 * @param {number} chunkIndex The index of the failed chunk.
	 * @private
	 */
	_manageRetries (chunkIndex)
	{
		const currentRetries = this.retriesMap.get(chunkIndex) || 0;
		if (currentRetries < this.retries)
		{
			this.retriesMap.set(chunkIndex, currentRetries + 1);

			setTimeout(() => {
				if (!this.hasError && !this.isAborted)
				{
					this.chunkQueue.unshift(chunkIndex);
					this._sendChunks();
				}
			}, this.delayBeforeRetry * 1000);

			this._eventEmitter.emit('fileRetry', {
				message: 'An error occurred uploading chunk ' + chunkIndex + '. ' + (this.retries - currentRetries - 1) + ' retries left',
				chunk: chunkIndex,
				retriesLeft: this.retries - currentRetries - 1
			});

			return;
		}

		this.hasError = true;
		this._eventEmitter.emit('error', 'An error occurred uploading part [' + chunkIndex + ']');
	}

	/**
	 * Handle the dispatching of concurrent chunk uploads.
	 *
	 * @private
	 * @returns {void}
	 */
	_sendChunks ()
	{
		if (this.hasError || this.isAborted)
		{
			return;
		}

		while (this.activeWorkers < this.concurrency && this.chunkQueue.length > 0)
		{
			const chunkIndex = this.chunkQueue.shift();
			this._uploadChunk(chunkIndex);
		}
	}
}

/**
 * An event emitter class that extends the EventTarget class.
 *
 * @class
 * @extends EventTarget
 */
class ChunkEventEmitter extends EventTarget
{
	constructor ()
	{
		super();
	}

	// Custom method to subscribe to events
	on (eventType, callback)
	{
		this.addEventListener(eventType, callback);
	}

	// Custom method to unsubscribe from events
	off (eventType, callback)
	{
		this.removeEventListener(eventType, callback);
	}

	// Custom method to emit events
	emit (eventType, eventData)
	{
		this.dispatchEvent(new CustomEvent(eventType, {detail: eventData}));
	}
}
