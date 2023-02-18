/*!
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1.9
 */

/**
 * This file contains javascript associated with the drag drop of files functionality
 * while posting
 */

/**
 * Simply invoke the constructor by calling new dragDropAttachment
 */
(function() {
	var dragDropAttachment = (function(params) {

		// Few internal global vars
		var allowedExtensions = [],
			curFileNum = 0,
			totalSizeAllowed = null,
			individualSizeAllowed = null,
			numOfAttachmentAllowed = null,
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
			// @deprecated since 1.1 - here just for backward compatibility
			fileDisplayTemplate = '<div class="statusbar"><div class="info"></div><div class="progressBar"><div></div></div><div class="control icon i-close"></div></div>',
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
					for (var event in params.events) {
						if (params.events.hasOwnProperty(event)) {
							addEventListener(event, params.events[event]);
						}
					}
				}

				allowedExtensions = params.allowedExtensions === '' ? [] : params.allowedExtensions.toLowerCase().replace(/\s/g, '').split(',');
				totalSizeAllowed = params.totalSizeAllowed || 0;
				individualSizeAllowed = params.individualSizeAllowed || 0;
				numOfAttachmentAllowed = params.numOfAttachmentAllowed || 0;
				totalAttachSizeUploaded = params.totalAttachSizeUploaded || 0;
				numAttachUploaded = params.numAttachUploaded || 0;
				resizeImageEnabled = params.resizeImageEnabled;
				filesUploadedSuccessfully = [];
				if (typeof params.topic !== 'undefined')
					topic = params.topic;
				if (typeof params.fileDisplayTemplate !== 'undefined')
					fileDisplayTemplate = params.fileDisplayTemplate;
				$str = $(fileDisplayTemplate);
				board = params.board;
				oTxt = params.oTxt;
				oTxt.totalSizeAllowed = oTxt.totalSizeAllowed.replace(/ KB\./ig, '.');
				if (typeof params.existingSelector !== 'undefined')
					processExisting($(params.existingSelector));
			},

			/**
			* private function
			*
			* Takes already uploaded files (e.g. when editing a message)
			* and creates the "D&D" interface
			*
			* @param {object} $files Array of elements of existing input files
			*/
			processExisting = function ($files) {
				$files.each(function(idx, value) {
					var status = new createStatusbar({}),
						$file = $(this);

					status.setFileNameSize($file.parent().text(), $file.data('size'));
					status.setProgress(100);

					var $button = status.getButton(),
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
			sendFileToServer = function(formData, status, fileSize, fileName) {
				var jqXHR = $.ajax({
					xhr: function() {
						var xhrobj = $.ajaxSettings.xhr();

						if (xhrobj.upload) {
							// Set up a progress event listener to update the UI
							xhrobj.upload.addEventListener('progress', function(event) {
								var percent = 0,
									position = event.loaded || event.position,
									total = event.total;

								if (event.lengthComputable)
									percent = Math.ceil(position / total * 100);

								status.setProgress(percent);
							}, false);
						}

						return xhrobj;
					},
					url: elk_scripturl + '?action=attachment;sa=ulattach;api;' + elk_session_var + '=' + elk_session_id + ';board=' + board,
					type: "POST",
					dataType: "json",
					contentType: false,
					processData: false,
					cache: false,
					data: formData,
					context: {
						'fileName': fileName,
						'fileSize': fileSize
					}
				}).done(function(resp) {
					if (typeof(resp) !== 'object')
						resp = JSON.parse(resp);

					// Well its done, lets make sure the server says so as well
					if (resp.result) {
						var curFileNum = filesUploadedSuccessfully.length,
							data = resp.data;

						// Show it as complete
						status.setProgress(100);
						filesUploadedSuccessfully.push(data);
						data.curFileNum = curFileNum;
						status.onUploadSuccess(data);

						// Correct the upload size
						if (resizeImageEnabled && fileSize !== data.size) {
							totalAttachSizeUploaded -= fileSize - data.size;
						}
					} else {
						// The server was unable to process the file, show it as not sent
						var errorMsgs = {},
							serverErrorFiles = [];

						for (var err in resp.data) {
							if (resp.data.hasOwnProperty(err)) {
								errorMsgs.individualServerErr = resp.data[err].title.php_unhtmlspecialchars() + '<br />';

								for (var errMsg in resp.data[err].errors) {
									if (resp.data[err].errors.hasOwnProperty(errMsg))
										serverErrorFiles.push(resp.data[err].errors[errMsg].php_unhtmlspecialchars());
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
				}).fail(function(e, textStatus, errorThrown) {
					var errorMsgs = {},
						sizeErrorFiles = [];

					numAttachUploaded--;
					totalAttachSizeUploaded -= fileSize;

					if (textStatus === 'abort')
					{
						errorMsgs.default = oTxt.uploadAbort;
					}
					else
					{
						errorMsgs.individualSizeErr = oTxt.postUploadError;
						sizeErrorFiles.push(this.fileName.php_htmlspecialchars());
					}
					populateErrors({
						'errorMsgs': errorMsgs,
						'sizeErrorFiles': sizeErrorFiles
					});
					status.setServerFail(0);

				}).always(function() {
					uploadInProgress = false;
					updateStatusText();
					runAttachmentQueue();
				});
				status.setAbort(jqXHR);
			},

		/**
		* private function
		*
		* Updates the restrictions text line with current values
		*/
		updateStatusText = function() {
			var numberAllowed = document.getElementById('attachmentNumPerPostLimit'),
				totalSize = document.getElementById('attachmentPostLimit');

			if (numberAllowed !== null)
				numberAllowed.textContent = String(params.numOfAttachmentAllowed - numAttachUploaded);

			if (totalSize !== null)
				totalSize.textContent = formatBytes(params.totalSizeAllowed - totalAttachSizeUploaded);
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
				return '0';

			for (let kb of ['Bytes', 'KB', 'MB', 'GB']) {
				if (bytes < 1024)
					return parseFloat(bytes.toFixed(2)) + ' ' + kb;

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
			return file && file['type'].split('/')[0] === 'image';
		},

		/**
		* private function
		*
		* Removes the file from the server that was successfully uploaded
		*
		* @param {object} options
		*/
		removeFileFromServer = function(options) {
			var dataToSend = filesUploadedSuccessfully[options.fileNum];

			// So you did not want to send that file?
			$.ajax({
				url: elk_scripturl + '?action=attachment;sa=rmattach;api;' + elk_session_var + '=' + elk_session_id,
				type: "POST",
				cache: false,
				dataType: 'json',
				data: {
					'attachid': dataToSend.attachid
				}
			}).done(function(resp) {
				if (typeof(resp) !== 'object')
					resp = JSON.parse(resp);

				// Make sure we have a result:true in the response
				if (resp.result) {
					// Update our counters, number of files allowed and total data payload
					totalAttachSizeUploaded -= filesUploadedSuccessfully[options.fileNum].size;
					numAttachUploaded--;
					triggerEvt('RemoveSuccess', options.control, [dataToSend.attachid]);

					// Done with this one, so remove it from existence
					$('#' + dataToSend.attachid).off().remove();
					updateStatusText();
				}
				else if ('console' in window)
					window.console.info(resp.data);
			}).fail(function(jqXHR, textStatus, errorThrown) {
				if ('console' in window) {
					window.console.info('Error:', textStatus, errorThrown.name);
					window.console.info(jqXHR.responseText);
				}
			});
		},

		/**
		* private function
		*
		* Creates the status UI for each file dropped
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
			var $control = $str.clone(),
				$button = $control.find('.control'),
				$progressbar = $control.find('.progressBar');
			$button.addClass('abort');

			$('.progress_tracker').append($control);

			// Provide the file size in something more legible, like 100KB or 1.1MB
			this.setFileNameSize = function(name, size) {
				var sizeStr = formatBytes(size);
				name = name.split(' (')[0].trim().php_htmlspecialchars();
				$control.find('.info').html(name + ' (' + sizeStr + ')');
			};

			// Set the progress bar position
			this.setProgress = function(progress) {
				var progressBarWidth = progress * $progressbar.width() / 100;

				$progressbar.find('div').animate({
					width: progressBarWidth
				}, 10).html(progress + "% ");

				// Completed upload, server is making thumbs, doing checks, resize, etc
				if (progress === 100) {
					$progressbar.find('div').html(oTxt.processing);
					$progressbar.after('<i class="icon i-spinner icon-spin"></i>');
				}
			};

			// Provide a way to stop the upload before it is done
			this.setAbort = function (jqxhr)
			{
				let sb = $control,
					pb = $progressbar;

				$button.on('click', function (e)
				{
					e.preventDefault();
					jqxhr.abort();
					sb.hide();
					pb.siblings('.i-spinner').remove();
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
				$progressbar.siblings('.icon .i-spinner .icon-spin').remove();
				$progressbar.css("background-color", "var(--warn)");
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
		 * Prepares the uploaded file area
		 *
		 * @param {object} $control
		 * @param {object} $button
		 * @param {object} data
		 */
		fileUploadedInterface = function($control, $button, data) {
			$button.off('click');
			$button.removeClass('abort i-close').addClass('remove i-delete');

			// Update the uploaded file with its ID
			$button.attr('id', data.curFileNum);
			$control.attr('id', data.attachid);
			$control.attr('data-size', data.size);

			// We may have changed the name and size if resize is enabled
			if (data.name)
				$control.find('.info').html(data.name + ' (' + formatBytes(data.size) + ')');

			// We need to tell Elk that the file should not be deleted
			$button.after($('<input />')
				.attr('type', 'hidden')
				.attr('name', 'attach_del[]')
				.attr('value', data.attachid));

			var $img = $('<img />').attr('src', elk_scripturl + '?action=dlattach;sa=tmpattach;attach=' + $control.attr('id') + ';topic=' + topic),
				$progressbar = $control.find('.progressBar');

			$progressbar.siblings('.i-spinner').remove();
			$progressbar.after($('<div class="postattach_thumb" />').append($img));
			$progressbar.remove();

			// Provide a way to remove a file that has been sent by mistake
			$button.on('click', function(e) {
				e.preventDefault();

				var fileNum = e.target.id;

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
			var errorMsgs = {},
				extnErrorFiles = [],
				sizeErrorFiles = [];

			// These will get checked again serverside, this is just to save some time/cycles
			for (var i = 0; i < files.length; i++) {
				var fileExtensionCheck = /(?:\.([^.]+))?$/,
					extension = fileExtensionCheck.exec(files[i].name)[1].toLowerCase(),
					fileSize = files[i].size,
					errorFlag = false;

				// Make sure the server will allow this type of file
				if (allowedExtensions.length > 0 && allowedExtensions.indexOf(extension) < 0) {
					errorMsgs.extnError = '(<strong>' + extension + '</strong>) ' + oTxt.allowedExtensions;
					extnErrorFiles.push(files[i].name);
					errorFlag = true;
				}

				// Make sure the file is not larger than the server will accept
				if (individualSizeAllowed !== 0 && fileSize > individualSizeAllowed && !resizeImageEnabled && isFileImage(files[i])) {
					errorMsgs.individualSizeErr = '(' + formatBytes(fileSize) + ' ) ' + oTxt.individualSizeAllowed;
					sizeErrorFiles.push(files[i].name);
					errorFlag = true;
				}

				// And you can't send too many
				if (numOfAttachmentAllowed !== 0 && numAttachUploaded >= numOfAttachmentAllowed) {
					errorMsgs.maxNumErr = oTxt.numOfAttachmentAllowed;
					sizeErrorFiles.push(files[i].name);
					errorFlag = true;
				}

				// Lets see if this will exceed the total file quota we allow
				if (errorFlag === false)
					totalAttachSizeUploaded += fileSize;

				if (totalSizeAllowed !== 0 && totalAttachSizeUploaded > totalSizeAllowed) {
					errorMsgs.totalSizeError = oTxt.totalSizeAllowed.replace("%1$s", formatBytes(totalSizeAllowed)).replace("%2$s", formatBytes(totalSizeAllowed - (totalAttachSizeUploaded - fileSize)));
					errorFlag = true;
					totalAttachSizeUploaded -= fileSize;
				}

				// No errors, so update the counters (number, total size, etc)
				// and add this file to the processing queue
				if (errorFlag === false) {
					var fd = new FormData(),
						status = new createStatusbar(obj);

					numAttachUploaded++;
					fd.append('attachment[]', files[i]);
					status.setFileNameSize(files[i].name.php_htmlspecialchars(), files[i].size);
					attachmentQueue.push({
						'formData': fd,
						'statusInstance': status,
						'fileName': files[i].name,
						'fileSize': files[i].size
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
			if (attachmentQueue.length > 0 && uploadInProgress === false) {
				setTimeout(function () {
					var currentData = attachmentQueue[0];

					uploadInProgress = true;
					sendFileToServer(currentData.formData, currentData.statusInstance, currentData.fileSize, currentData.fileName);
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
			var $drop_attachments_error = $('.drop_attachments_error');

			$drop_attachments_error.html('');

			var wrapper = '<p class="warningbox">';

			for (var err in params.errorMsgs) {
				if (params.errorMsgs.hasOwnProperty(err)) {
					// Build the warning box of errors this file generated
					switch (err) {
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
		* private function
		*
		* Used to check if a value exists in an array
		*
		* @param {string} needle
		*/
		indexOf = function(needle) {
			if (typeof Array.prototype.indexOf === 'function')
				indexOf = Array.prototype.indexOf;
			else {
				indexOf = function(needle) {
					var i,
						index = -1;

					for (i = 0; i < this.length; i++) {
						if (this[i] === needle) {
							index = i;
							break;
						}
					}

					return index;
				};
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
				oEvents[event] = [];

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
				return;

			for (var i = 0; i < oEvents[event].length; i++) {
				oEvents[event][i].apply(aThis, args);
			}
		};

		/**
		* Initialize the drag and drop function!
		*/
		$(function() {
			var obj = $(".drop_area");

			// Make sure the browser supports this
			if (!(window.FormData && ("onprogress" in $.ajaxSettings.xhr())))
				return;

			// All clear, show the drop zone
			obj.toggle();
			$('.drop_attachments_no_js').hide();

			// Entering the dropzone, show it
			obj.on('dragenter', function(e) {
				e.stopPropagation();
				e.preventDefault();
				$(this).css('opacity', '1');
			});

			// Hovering over, waiting waiting waiting, show we are waiting
			obj.on('dragover', function(e) {
				e.stopPropagation();
				e.preventDefault();
			});

			// Catch what you dropped, and send it off to be processed
			obj.on('drop', function(e) {
				var files = e.originalEvent.dataTransfer.files;

				e.preventDefault();
				$(this).css('opacity', '0.6');
				handleFileUpload(files, obj);
			});

			// Wait, where are you going?  Lets show you are outside the zone
			obj.on('dragexit', function(e) {
				e.preventDefault();
				$(this).css('opacity', '0.6');
			});

			// Rather click and select?
			var $input = obj.find('#attachment_click');
			$input.on('change', function(e) {
				e.preventDefault();
				var files = $(this)[0].files;
				handleFileUpload(files, obj);
				this.value = null;
			});
			$input.clone(true, true).appendTo('.drop_area_fileselect_text');
			$input.hide();
		});
		init(params);
		return {
			init: init,
			addEventListener: addEventListener,
			handleFileUpload: handleFileUpload
		};
	});

	// AMD / RequireJS
	if ( typeof define !== 'undefined' && define.amd) {
		define([], function() {
			return dragDropAttachment;
		});
	}
	// CommonJS
	else if ( typeof module !== 'undefined' && module.exports) {
		module.exports = dragDropAttachment;
	}
	// included directly via <script> tag
	else {
		this.dragDropAttachment = dragDropAttachment;
	}
}());
