/*!
 * @package   ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause (see accompanying LICENSE.txt file)
 *
 * @version 2.0 dev
 */

/**
 * This file contains javascript associated with the drag drop of files functionality
 * while posting
 */

/**
 * Simply invoke the constructor by calling new dragDropAttachment
 */
(function() {
	const dragDropAttachment = (function(params) {
		// Few internal global vars
		let allowedExtensions = [],
			curFileNum = 0,
			totalSizeAllowed = null,
			individualSizeAllowed = null,
			numOfAttachmentAllowed = null,
			attachmentChunkSize = null,
			totalAttachSizeUploaded = 0,
			numAttachUploaded = 0,
			filesUploadedSuccessfully = [],
			uploadInProgress = false,
			attachmentQueue = [],
			resizeImageEnabled = false,
			board = 0,
			topic = 0,
			oTxt = {},
			errorMsg = '',
			oEvents = {},
			$str,

			/**
			 * public function, accessible with prototype chain
			 * @param {object} params
			 *
			 *    allowedExtensions - types of attachments allowed
			 *    totalSizeAllowed - maximum size of total attachments allowed
			 *    individualSizeAllowed - maximum individual file size allowed
			 *    numOfAttachmentAllowed - number of files that can be attached in a post
			 *    totalAttachSizeUploaded - total size of already attached files(modifying post)
			 *    numAttachUploaded - number of already attached files(modifying post)
			 */
			init = function(params) {
				if (typeof params.events !== 'undefined')
				{
					for (let event in params.events)
					{
						if (params.events.hasOwnProperty(event))
						{
							addEventListener(event, params.events[event]);
						}
					}
				}

				allowedExtensions = params.allowedExtensions === '' ? [] : params.allowedExtensions.toLowerCase().replace(/\s/g, '').split(',');
				totalSizeAllowed = params.totalSizeAllowed || 0;
				individualSizeAllowed = params.individualSizeAllowed || 0;
				numOfAttachmentAllowed = params.numOfAttachmentAllowed || 0;
				totalAttachSizeUploaded = params.totalAttachSizeUploaded || 0;
				attachmentChunkSize = params.totalAttachSizeUploaded || 250000;
				numAttachUploaded = params.numAttachUploaded || 0;
				resizeImageEnabled = params.resizeImageEnabled;
				filesUploadedSuccessfully = [];
				topic = params.topic ? params.topic : topic;
				$str = $(params.fileDisplayTemplate);
				board = params.board;
				oTxt = params.oTxt;
				oTxt.totalSizeAllowed = oTxt.totalSizeAllowed.replace(/ KB\./ig, '.');
				if (typeof params.existingSelector !== 'undefined')
				{
					processExisting($(params.existingSelector));
				}
			},

			/**
			 * private function
			 *
			 * Takes already uploaded files (e.g. when editing a message)
			 * and creates the "D&D" interface
			 *
			 * @param {object} $files Array of elements of existing input files
			 */
			processExisting = function($files) {
				$files.each(function(idx, value) {
					let status = new createStatusbar({}),
						$file = $(this);

					status.setFileNameSize($file.data('name'), $file.data('size'));
					status.setProgress(100);

					let $button = status.getButton(),
						data = {
							curFileNum: curFileNum++,
							attachid: $file.data('attachid'),
							size: $file.data('size')
						};
					$button.addClass('abort');
					status.onUploadSuccess(data);
					filesUploadedSuccessfully.push(data);
					$file.closest('dd').remove();
				});
			},

			/**
			 * private function
			 *
			 * Uploads the file to server and updates the UI
			 *
			 * @param {object} formData current file with data to upload
			 * @param {object} status current progress bar UI instance
			 * @param {int} fileSize current progress bar UI instance
			 * @param {string} fileName current progress bar UI instance
			 */
			sendFileToServer = function(formData, status) {
				const abortController = new AbortController();
				const abortSignal = abortController.signal;

				let fileName = formData.get('attachment[]').name,
					fileSize = formData.get('attachment[]').size,
					url = elk_prepareScriptUrl(elk_scripturl) + 'action=attachment;sa=ulasync;api=json;;board=' + board;
				try
				{
					const uploader = new chunkUpload({url: url, form: formData, chunkSize: attachmentChunkSize, signal: abortSignal})
						.on('progress', (progress) => {
							status.setProgress(progress.detail);
						})
						.on('complete', (resp) => {
							if (resp.detail.result === true)
							{
								uploader.finalize();
							}
						})
						.on('done', (resp) => {
							// Well it is done, lets make sure the server says so as well
							if (resp.detail.result)
							{
								let curFileNum = filesUploadedSuccessfully.length,
									data = resp.detail.data;

								// Show it as complete
								filesUploadedSuccessfully.push(data);
								data.curFileNum = curFileNum;
								status.onUploadSuccess(data);

								// Correct the upload size
								if (resizeImageEnabled && fileSize !== data.size)
								{
									totalAttachSizeUploaded -= fileSize - data.size;
								}
							}
							else
							{
								// The server was unable to process the file, show it as not sent
								let errorMsgs = {},
									serverErrorFiles = [];

								for (let err in resp.detail.data)
								{
									if (resp.detail.data.hasOwnProperty(err))
									{
										errorMsgs.individualServerErr = resp.detail.data[err].title.php_unhtmlspecialchars() + '<br />';

										for (let errMsg in resp.detail.data[err].errors)
										{
											if (resp.detail.data[err].errors.hasOwnProperty(errMsg))
											{
												serverErrorFiles.push(resp.detail.data[err].errors[errMsg].php_unhtmlspecialchars());
											}
										}
									}
									numAttachUploaded--;
									totalAttachSizeUploaded -= fileSize;
									populateErrors({
										'errorMsgs': errorMsgs,
										'serverErrorFiles': serverErrorFiles
									});
								}
								status.setServerFail(0);
							}
						})
						.on('error', (err) => {
							let errorMsgs = {},
								sizeErrorFiles = [];

							numAttachUploaded--;
							totalAttachSizeUploaded -= fileSize;

							if (err.detail === 'abort')
							{
								errorMsgs.default = oTxt.uploadAbort;
							}
							else
							{
								errorMsgs.individualSizeErr = oTxt.postUploadError;
								sizeErrorFiles.push(fileName.php_htmlspecialchars());
							}

							populateErrors({
								'errorMsgs': errorMsgs,
								'sizeErrorFiles': sizeErrorFiles
							});

							status.setServerFail(0);
						})
						.on('always', () => {
							uploadInProgress = false;
							updateStatusText();
							runAttachmentQueue();
						});
				}
				catch (error)
				{
					if (error instanceof TypeError && 'console' in window && console.info)
					{
						console.error('There was a TypeError:', error);
					}
					else
					{
						// Re-throw the error if it's not a TypeError
						throw error;
					}
				}
				status.setProgress(0);
				status.setAbort(abortController);
			},

			/**
			 * private function
			 *
			 * Updates the restrictions text line with current values
			 */
			updateStatusText = function() {
				let numberAllowed = document.getElementById('attachmentNumPerPostLimit'),
					totalSize = document.getElementById('attachmentPostLimit');

				if (numberAllowed !== null)
				{
					numberAllowed.textContent = String(params.numOfAttachmentAllowed - numAttachUploaded);
				}

				if (totalSize !== null)
				{
					totalSize.textContent = formatBytes(params.totalSizeAllowed - totalAttachSizeUploaded);
				}
			},

			/**
			 * private function
			 *
			 * Takes a number in bytes and returns a formatted string
			 *
			 * @param bytes
			 * @returns {string}
			 */
			formatBytes = function(bytes) {
				if (bytes === 0)
				{
					return '0';
				}

				for (let kb of ['Bytes', 'KB', 'MB', 'GB', 'TB'])
				{
					if (bytes < 1024)
					{
						return parseFloat(bytes.toFixed(2)) + ' ' + kb;
					}

					bytes /= 1024;
				}
			},

			/**
			 * private function
			 *
			 * Checks if the type is one of image/xyz
			 *
			 * @param file
			 * @returns {boolean}
			 */
			isFileImage = function(file) {
				return file && file.type.split('/')[0] === 'image';
			},

			/**
			 * private function
			 *
			 * Removes the file from the server that was successfully uploaded
			 *
			 * @param {object} options
			 */
			removeFileFromServer = function(options) {
				const postForm = new FormData();

				let attachid = filesUploadedSuccessfully[options.fileNum].attachid;

				postForm.append('attachid', attachid);
				postForm.append(elk_session_var, elk_session_id);

				fetch(elk_prepareScriptUrl(elk_scripturl) + 'action=attachment;sa=rmattach;api=json;', {
					method: 'POST',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
						'Accept': 'application/json'
					},
					body: postForm,
				})
					.then(response => {
						if (!response.ok)
						{
							throw new Error('Http error:' + response.status);
						}

						return response.json();
					})
					.then(resp => {
						if (resp.result)
						{
							totalAttachSizeUploaded -= filesUploadedSuccessfully[options.fileNum].size;
							numAttachUploaded--;
							triggerEvt('RemoveSuccess', options.control, [attachid]);
							document.getElementById(attachid).remove();
							updateStatusText();
						}
						else if ('console' in window && console.info)
						{
							console.info(resp.data);
						}
					})
					.catch((error) => {
						if ('console' in window && console.info)
						{
							console.info('Error:', error.message);
						}
					});
			},

			/**
			 * private function
			 *
			 * Creates the status UI for each file added
			 * Initiate as new createStatusbar
			 * Has the following methods available to it
			 *  - setFileNameSize
			 *  - setProgress
			 *  - setAbort
			 *  - setServerFail
			 *  - onUploadSuccess
			 * @param {object} obj options
			 */
			createStatusbar = function(obj) {
				let $control = $str.clone(),
					$button = $control.find('.control'),
					$progressbar = $control.find('.progressBar');

				$button.addClass('abort');

				$('.progress_tracker').append($control);

				// Provide the file size in something more legible, like 100KB or 1.1MB
				this.setFileNameSize = function(name, size) {
					let sizeStr = formatBytes(size);
					$control.find('.info').html(name + ' (' + sizeStr + ')');
				};

				// Set the progress bar position
				this.setProgress = function(progress) {
					let progressBarWidth = progress * $progressbar.width() / 100,
						progressBarDiv = $progressbar.find('div');

					if (progressBarWidth === 0)
					{
						progressBarDiv.css('width', '0');
					}
					else
					{
						progressBarDiv.animate({
							width: progressBarWidth
						}, 250).html(progress + '% ');
					}

					// Completed upload, server is making thumbs, resizing, rotating, doing checks, etc
					if (progress === 100)
					{
						$progressbar.find('div').html(oTxt.processing);
						$progressbar.after('<i class="icon i-concentric"></i>');
					}
				};

				// Provide a way to stop the upload before it is done
				this.setAbort = function(abortController) {
					let sb = $control,
						pb = $progressbar;

					$button.on('click', function(e) {
						e.preventDefault();
						abortController.abort();
						sb.hide();
						pb.siblings('.i-concentric').remove();
						populateErrors({});
					});
				};

				this.getButton = function() {
					return $button;
				};

				// Server Failure is always an option when sending files
				this.setServerFail = function(data) {
					this.setProgress(0);
					$button.removeClass('i-close').addClass('i-alert');
					$progressbar.siblings('.i-concentric').remove();
					$progressbar.css('background-color', 'var(--warn)');
				};

				// The file upload is successful, remove our abort event and swap the class
				this.onUploadSuccess = function(data) {
					fileUploadedInterface($control, $button, data);
					triggerEvt('UploadSuccess', $control, [$button, data]);
				};
			},

			/**
			 * private function
			 *
			 * Prepares the uploaded file area to show the upload status / results
			 *
			 * @param {object} $control
			 * @param {object} $button
			 * @param {object} data
			 */
			fileUploadedInterface = function($control, $button, data) {
				$button.off('click');
				$button.removeClass('abort i-close').addClass('remove i-delete colorize-delete');

				// Update the uploaded file with its ID
				$button.attr('id', data.curFileNum);
				$control.attr('id', data.attachid);
				$control.attr('data-size', data.size);

				// We may have changed the name and size if resize is enabled
				if (data.name)
				{
					$control.find('.info').html(data.name + ' (' + formatBytes(data.size) + ')');
				}

				// We need to tell Elk that the file should not be deleted
				$button.after($('<input />')
					.attr('type', 'hidden')
					.attr('name', 'attach_del[]')
					.attr('value', data.attachid));

				let $img = $('<img />').attr('src', elk_scripturl + '?action=dlattach;sa=tmpattach;attach=' + $control.attr('id') + ';topic=' + topic),
					$progressbar = $control.find('.progressBar');

				$progressbar.siblings('.i-concentric').remove();
				$progressbar.after($('<div class="postattach_thumb" />').append($img));
				$progressbar.remove();

				// Provide a way to remove a file that has been sent by mistake
				$button.on('click', function(e) {
					e.preventDefault();

					let fileNum = e.target.id;
					if (confirm(oTxt.areYouSure))
					{
						removeFileFromServer({
							'fileNum': fileNum,
							'control': $control
						});

						populateErrors({});
					}
				});
			},

			/**
			 * public function
			 *
			 * Handle the functionality when file(s) are dropped
			 *
			 * @param {object} files what files to upload
			 * @param {object} obj parent object in which file progress is shown
			 */
			handleFileUpload = function(files, obj) {
				let errorMsgs = {},
					extnErrorFiles = [],
					sizeErrorFiles = [];

				// These will get checked again serverside, this is just to save some time/cycles
				for (let i = 0; i < files.length; i++)
				{
					// valid extensions only
					let fileExtensionCheck = /(?:\.([^.]+))?$/,
						extension = fileExtensionCheck.exec(files[i].name)[1].toLowerCase(),
						fileSize = files[i].size,
						errorFlag = false;

					// Make sure the server will allow this type of file
					if (allowedExtensions.length > 0 && !allowedExtensions.includes(extension))
					{
						errorMsgs.extnError = '(<strong>' + extension + '</strong>) ' + oTxt.allowedExtensions;
						extnErrorFiles.push(files[i].name);
						errorFlag = true;
					}

					// Make sure the file is not larger than the server will accept
					if (individualSizeAllowed !== 0 && fileSize > individualSizeAllowed && !resizeImageEnabled && isFileImage(files[i]))
					{
						errorMsgs.individualSizeErr = '(' + formatBytes(fileSize) + ' ) ' + oTxt.individualSizeAllowed;
						sizeErrorFiles.push(files[i].name);
						errorFlag = true;
					}

					// And you can't send too many
					if (numOfAttachmentAllowed !== 0 && numAttachUploaded >= numOfAttachmentAllowed)
					{
						errorMsgs.maxNumErr = oTxt.numOfAttachmentAllowed;
						sizeErrorFiles.push(files[i].name);
						errorFlag = true;
					}

					// Let's see if this will exceed the total file quota we allow
					if (errorFlag === false)
					{
						totalAttachSizeUploaded += fileSize;
					}

					if (totalSizeAllowed !== 0 && totalAttachSizeUploaded > totalSizeAllowed)
					{
						errorMsgs.totalSizeError = oTxt.totalSizeAllowed.replace('%1$s', formatBytes(totalSizeAllowed)).replace('%2$s', formatBytes(totalSizeAllowed - (totalAttachSizeUploaded - fileSize)));
						errorFlag = true;
						totalAttachSizeUploaded -= fileSize;
					}

					// No errors, so update the counters (number, total size, etc)
					// and add this file to the processing queue
					if (errorFlag === false)
					{
						let fd = new FormData(),
							statusBar = new createStatusbar(obj);

						numAttachUploaded++;
						fd.append('attachment[]', files[i]);
						statusBar.setFileNameSize(files[i].name.php_htmlspecialchars(), files[i].size);
						attachmentQueue.push({
							'formData': fd,
							'statusInstance': statusBar
						});
					}
				}

				// Time to show any errors for this batch of files
				populateErrors({
					'errorMsgs': errorMsgs,
					'extnErrorFiles': extnErrorFiles,
					'sizeErrorFiles': sizeErrorFiles
				});

				runAttachmentQueue();
			},

			/**
			 * private function
			 *
			 * Checks if there are any files pending upload
			 */
			runAttachmentQueue = function() {
				if (attachmentQueue.length > 0 && uploadInProgress === false)
				{
					setTimeout(function() {
						let currentData = attachmentQueue[0];

						uploadInProgress = true;
						sendFileToServer(currentData.formData, currentData.statusInstance);
						attachmentQueue.splice(0, 1);
					}, 200);
				}
			},

			/**
			 * private function
			 *
			 * Populates the warning box when something does not go as expected
			 *
			 * @param {object} params
			 *    error messages to show
			 *    file names having extension error
			 *    file names having size error
			 */
			populateErrors = function(params) {
				let $drop_attachments_error = $('.drop_attachments_error');

				$drop_attachments_error.html('');

				let wrapper = '<p class="warningbox">';

				for (let err in params.errorMsgs)
				{
					if (params.errorMsgs.hasOwnProperty(err))
					{
						// Build the warning box of errors this file generated
						switch (err)
						{
							case 'extnError':
								errorMsg = wrapper + params.extnErrorFiles.join(', ') + ' : ' + params.errorMsgs[err] + '</p>';
								break;
							case 'individualSizeErr':
								errorMsg = wrapper + params.sizeErrorFiles.join(', ') + ' : ' + params.errorMsgs[err] + '</p>';
								break;
							case 'individualServerErr':
								errorMsg = wrapper + params.errorMsgs[err] + params.serverErrorFiles.join(', ') + '</p>';
								break;
							default:
								errorMsg = wrapper + params.errorMsgs[err] + '</p>';
								break;
						}
					}

					// Show them what they are doing wrong
					$drop_attachments_error.append(errorMsg);
				}
			},

			/**
			 * public function
			 *
			 * Used to extend the code
			 *
			 * @param {string} event
			 * @param {object} listener
			 */
			addEventListener = function(event, listener) {
				if (!oEvents.hasOwnProperty(event))
				{
					oEvents[event] = [];
				}

				oEvents[event].push(listener);
			},

			/**
			 * private function
			 *
			 * Runs all the listeners on a certain event
			 *
			 * @param {string} event
			 * @param {object} aThis
			 * @param {object} args
			 */
			triggerEvt = function(event, aThis, args) {
				if (!oEvents.hasOwnProperty(event))
				{
					return;
				}

				for (let i = 0; i < oEvents[event].length; i++)
				{
					oEvents[event][i].apply(aThis, args);
				}
			};

		/**
		 * Initialize the drag and drop function!
		 */
		window.onload = function() {
			const obj = document.querySelector('.drop_area');

			// Make sure the browser supports this
			if (!(window.FormData && window.fetch))
			{
				return;
			}

			// All clear, show the drop zone
			obj.style.display = 'block';
			document.querySelectorAll('.drop_attachments_no_js').forEach(element => element.style.display = 'none');

			// Entering the dropzone, show it
			obj.addEventListener('dragenter', function(e) {
				e.stopPropagation();
				e.preventDefault();

				obj.style.opacity = '1';
			});

			// Hovering over, waiting waiting waiting, show we are waiting
			obj.addEventListener('dragover', function(e) {
				e.stopPropagation();
				e.preventDefault();
			});

			// Catch what you dropped, and send it off to be processed
			obj.addEventListener('drop', function(e) {
				let files = e.dataTransfer.files;
				e.preventDefault();
				obj.style.opacity = '0.6';
				handleFileUpload(files, obj);
			});

			// Wait, where are you going?  Lets show you are outside the zone
			obj.addEventListener('dragexit', function(e) {
				e.preventDefault();
				obj.style.opacity = '0.6';
			});

			// Rather click and select?
			const input = document.querySelector('#attachment_click');
			const cloneElem = input.cloneNode(true);

			document.querySelector('.drop_area_fileselect_text').appendChild(cloneElem);
			cloneElem.addEventListener('change', function(e) {
				e.preventDefault();
				const files = this.files;
				handleFileUpload(files, obj);
				this.value = null;
			});
			input.style.display = 'none';
		};

		init(params);
		return {
			init: init,
			addEventListener: addEventListener,
			handleFileUpload: handleFileUpload
		};
	});

	this.dragDropAttachment = dragDropAttachment;
}());
