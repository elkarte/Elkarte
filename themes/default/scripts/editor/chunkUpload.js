/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with the chunked upload functionality
 */
class chunkUpload
{
	constructor (params)
	{
		this.url = params.url;
		this.form = params.form;
		this.chunkSize = params.chunkSize || 250000;
		this.retries = params.retries || 4;
		this.delayBeforeRetry = params.delayBeforeRetry || 5;
		this.signal = params.signal;

		// Ensure the params are valid
		this._validateParams();

		// Setup for this file
		this._init();
		this._reader = new FileReader();
		this._eventEmitter = new ChunkEventEmitter();

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
	on (eType, fn)
	{
		this._eventEmitter.on(eType, fn);

		return this;
	}

	/**
	 * Sends a finalize request to the server.
	 * It appends necessary data to FormData and sends a POST request to the specified URL.
	 * Throws an error in case of a network error.
	 */
	finalize()
	{
		const combineChunkForm = new FormData();

		combineChunkForm.append('elkuuid', this.uuid);
		combineChunkForm.append('elkchunkindex', this.chunkCount);
		combineChunkForm.append('elktotalchunkcount', this.totalChunks);
		combineChunkForm.append('filename', this.file.name.php_urlencode());
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
		this.start = 0;
		this.retriesCount = 0;
		this.chunkData = null;
		this.chunkCount = 0;
		this.totalChunks = this._getTotalChunks();
		this.uuid = this._getUniqueId();
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
	 * Retrieves the next chunk of data from the file.
	 *
	 * @returns {Promise<unknown>} A promise that resolves when the chunk is retrieved.
	 * @private
	 */
	_getChunk ()
	{
		return new Promise((resolve) => {
			const length = this.totalChunks === 1 ? this.file.size : this.chunkSize;
			const start = length * this.chunkCount;

			this._reader.onload = () => {
				this.chunkData = new Blob([this._reader.result], {type: 'application/octet-stream'});
				resolve();
			};

			this._reader.readAsArrayBuffer(this.file.slice(start, start + length));
		});
	}

	/**
	 * Sends a chunk of data to the server.
	 *
	 * @private
	 * @returns {Promise<Response>} - A Promise that resolves to a Response object.
	 */
	_sendChunk ()
	{
		const chunkForm = new FormData();

		// Load the form with useful data
		chunkForm.append('elkchunkindex', this.chunkCount);
		chunkForm.append('elktotalchunkcount', this.totalChunks);
		chunkForm.append('elkuuid', this.uuid);
		chunkForm.append('filename', this.file.name.php_urlencode());
		chunkForm.append('filesize',  this.chunkData.size);
		chunkForm.append('filetype', this.file.type);
		chunkForm.append('attachment[]', this.chunkData);
		chunkForm.append(elk_session_var, elk_session_id);

		// Provide a way for the user to abort the upload
		let signal = this.signal;

		return fetch(this.url, {
			signal,
			method: 'POST',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Accept': 'application/json',
			},
			body: chunkForm,
			cache: 'no-store'
		});
	}

	/**
	 * Manages retries for uploading a chunk of a file.
	 *
	 * @private
	 */
	_manageRetries ()
	{
		if (this.retriesCount++ < this.retries)
		{
			setTimeout(() => this._sendChunks(), this.delayBeforeRetry * 1000);

			this._eventEmitter.emit('fileRetry', {
				message: 'An error occurred uploading chunk ' + this.chunkCount + '. ' + (this.retries - this.retriesCount) + ' retries left',
				chunk: this.chunkCount,
				retriesLeft: this.retries - this.retriesCount
			});

			return;
		}

		this._eventEmitter.emit('error', 'An error occurred uploading part [' + this.chunkCount + ']');
	}

	/**
	 * Handle the sending of all chunks of data.
	 *
	 * @private
	 * @returns {void}
	 */
	_sendChunks ()
	{
		this._getChunk()
			.then(() => this._sendChunk())
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
				if (response.result === true)
				{
					if (++this.chunkCount < this.totalChunks)
					{
						this._sendChunks();
					}
					else
					{
						this._eventEmitter.emit('complete', response);
					}

					const percentProgress = Math.round((100 / this.totalChunks) * this.chunkCount);
					this._eventEmitter.emit('progress', percentProgress);
				}
				else
				{
					let error = new Error('Error: ' + response.error);
					error.cause = {'status': 200};
					throw error;
				}
			})
			.catch((response) => {
				if (response.name === 'AbortError')
				{
					this._eventEmitter.emit('error', 'abort');
				}
				else if (response.cause && [408, 502, 503, 504].includes(response.cause.status))
				{
					this._manageRetries();
				}
				else
				{
					this._eventEmitter.emit('error', response.message);
				}
			});
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
