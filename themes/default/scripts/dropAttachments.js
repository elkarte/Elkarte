/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0.10
 *
 * This file contains javascript associated with the drag drop of files functionality
 * while posting
 */

/**
 * Simply invoke the constructor by calling dragDropAttachment
 */
(function() {
	function dragDropAttachment() {}

	dragDropAttachment.prototype = function() {

		// Few internal global vars
		var allowedExtensions = [],
			totalSizeAllowed = null,
			individualSizeAllowed = null,
			numOfAttachmentAllowed = null,
			totalAttachSizeUploaded = 0,
			numAttachUploaded = 0,
			filesUploadedSuccessfully = [],
			uploadInProgress = false,
			attachmentQueue = [],
			board = 0,
			oTxt = {},

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
				allowedExtensions = (params.allowedExtensions === '') ? [] : params.allowedExtensions.toLowerCase().replace(/\s/g, '').split(',');
				totalSizeAllowed = (params.totalSizeAllowed === '') ? null : params.totalSizeAllowed;
				individualSizeAllowed = (params.individualSizeAllowed === '') ? null : params.individualSizeAllowed;
				numOfAttachmentAllowed = (params.numOfAttachmentAllowed === '') ? null : params.numOfAttachmentAllowed;
				totalAttachSizeUploaded = params.totalAttachSizeUploaded / 1024;
				numAttachUploaded = params.numAttachUploaded;
				filesUploadedSuccessfully = [];
				board = params.board;
				oTxt = params.oTxt;
			},

			/**
			 * private function
			 *
			 * Uploads the file to server and updates the UI
			 *
			 * @param {object} formData current file with data to upload
			 * @param {object} status current progress bar UI instance
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

						// Show its done
						status.setProgress(100);
						filesUploadedSuccessfully.push(data);
						data.curFileNum = curFileNum;
						status.onUploadSuccess(data);
					} else {
						// The server was unable to process the file, show it as not sent
						var errorMsgs = {},
							serverErrorFiles = [];

						for (var err in resp.data) {
							if (resp.data.hasOwnProperty(err)) {
								errorMsgs.individualServerErr = resp.data[err].title + '<br />';

								for (var errMsg in resp.data[err].errors) {
									if (resp.data[err].errors.hasOwnProperty(errMsg))
										serverErrorFiles.push(resp.data[err].errors[errMsg]);
								}
							}
							numAttachUploaded--;

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
					errorMsgs.individualSizeErr = oTxt.postUploadError;
					sizeErrorFiles.push(this.fileName);
					populateErrors({
						'errorMsgs': errorMsgs,
						'sizeErrorFiles': sizeErrorFiles
					});
					status.setServerFail(0);

				}).always(function() {
					uploadInProgress = false;
					runAttachmentQueue();
				});
				status.setAbort(jqXHR);
			};

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
					totalAttachSizeUploaded -= filesUploadedSuccessfully[options.fileNum].size / 1024;
					numAttachUploaded--;

					// Done with this one, so remove it from existence
					$('#' + dataToSend.attachid).unbind();
					$('#' + dataToSend.attachid).remove();
				} else
					console.log('error success');
			}).fail(function(jqXHR, textStatus, errorThrown) {
				console.log(jqXHR);
				console.log(textStatus);
				console.log(errorThrown);
			});
		};

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
			this.str = $('<div class="statusbar"><div class="info"></div><div class="progressBar"><div></div></div><div class="abort fa fa-times-circle"></div></div>');

			$('.progress_tracker').append(this.str);

			// Provide the file size in something more legible, like 100KB or 1.1MB
			this.setFileNameSize = function(name, size) {
				var sizeStr = "",
					sizeKB = size / 1024,
					sizeMB = sizeKB / 1024;

				if (parseInt(sizeKB, 10) > 1024)
					sizeStr = sizeMB.toFixed(2) + " MB";
				else
					sizeStr = sizeKB.toFixed(2) + " KB";

				$(this.str).find('.info').html(name + ' (' + sizeStr + ')');
			};

			// Set the progress bar position
			this.setProgress = function(progress) {
				var progressBarWidth = progress * $(this.str).find('.progressBar').width() / 100;

				$(this.str).find('.progressBar div').animate({
					width: progressBarWidth
				}, 10).html(progress + "% ");
			};

			// Provide a way to stop the upload before its done
			this.setAbort = function(jqxhr) {
				var sb = $(this.str);

				$(this.str).find('.abort').bind('click', function(e) {
					e.preventDefault();
					jqxhr.abort();
					sb.hide();
				});
			};

			// Server Failure is always an option when sending files
			this.setServerFail = function(data) {
				this.setProgress(0);
				$(this.str).find('.abort').removeClass('fa-times-circle').addClass(' fa-exclamation-triangle');
			};

			// The file upload is successful, remove our abort event and swap the class
			this.onUploadSuccess = function(data) {
				$(this.str).find('.abort').unbind('click');
				$(this.str).find('.abort').removeClass('abort fa-times-circle').addClass('remove fa-minus-circle');

				// Update the uploaded file with its ID
				$(this.str).find('.remove').attr('id', data.curFileNum);
				$(this.str).attr('id', data.attachid);
				$(this.str).attr('data-size', data.size);

				// We need to tell Elk that the file should not be deleted
				$(this.str).find('.remove').after($('<input />')
					.attr('type', 'hidden')
					.attr('name', 'attach_del[]')
					.attr('value', data.attachid));

				// Provide a way to remove a file that has been sent by mistake
				$(this.str).find('.remove').bind('click', function(e) {
					e.preventDefault();

					var fileNum = e.target.id;

					removeFileFromServer({
						'fileNum': fileNum
					});
				});
			};
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

			for (var i = 0; i < files.length; i++) {
				var fileExtensionCheck = /(?:\.([^.]+))?$/,
					extension = fileExtensionCheck.exec(files[i].name)[1].toLowerCase(),
					fileSize = files[i].size / 1024,
					errorFlag = false;

				// Make sure the server will allow this type of file
				if (allowedExtensions.length > 0 && allowedExtensions.indexOf(extension) < 0) {
					errorMsgs.extnError = '(<strong>' + extension + '</strong>) ' + oTxt.allowedExtensions;
					extnErrorFiles.push(files[i].name);
					errorFlag = true;
				}

				// Make sure the file is not larger than the server will accept
				if (individualSizeAllowed !== null && fileSize > individualSizeAllowed) {
					errorMsgs.individualSizeErr = '(' + parseInt(fileSize, 10) + ' KB) ' + oTxt.individualSizeAllowed;
					sizeErrorFiles.push(files[i].name);
					errorFlag = true;
				}

				// And you can't send too many
				if (numAttachUploaded >= numOfAttachmentAllowed) {
					errorMsgs.maxNumErr = oTxt.numOfAttachmentAllowed;
					sizeErrorFiles.push(files[i].name);
					errorFlag = true;
				}

				// Lets see if this will exceed the total file quota we allow
				if (errorFlag === false)
					totalAttachSizeUploaded += fileSize;

				if (totalSizeAllowed !== null && totalAttachSizeUploaded > totalSizeAllowed) {
					errorMsgs.totalSizeError = oTxt.totalSizeAllowed.replace("%1$s", totalSizeAllowed).replace("%2$s", parseInt(totalSizeAllowed - (totalAttachSizeUploaded - fileSize), 10));
					errorFlag = true;
				}

				// No errors, so update the counters (number, total size, etc)
				// and add this file to the processing queue
				if (errorFlag === false) {
					var fd = new FormData(),
						status = new createStatusbar(obj);

					numAttachUploaded++;
					fd.append('attachment[]', files[i]);
					status.setFileNameSize(files[i].name, files[i].size);
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
				var currentData = attachmentQueue[0];

				uploadInProgress = true;
				sendFileToServer(currentData.formData, currentData.statusInstance, currentData.fileSize, currentData.fileName);
				attachmentQueue.splice(0, 1);
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
			$('.drop_attachments_error').html('');

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
				$('.drop_attachments_error').append(errorMsg);
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
					var i = -1,
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

			return indexOf.call(this, needle);
		};

		return {
			init: init,
			handleFileUpload: handleFileUpload
		};
	}();

	/**
	 * Initialize the drag and drop function!
	 */
	$(document).ready(function() {
		var obj = $(".drop_area");

		// Make sure the browser supports this
		if (!(window.FormData && ("onprogress" in $.ajaxSettings.xhr())))
			return;

		// Don't attach D&D on small screens
		if (!window.matchMedia || window.matchMedia('(max-width: 33.750em)').matches)
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
			dragDropAttachment.prototype.handleFileUpload(files, obj);
		});

		// Wait, where are you going?  Lets show you are outside the zone
		obj.on('dragexit', function(e) {
			e.preventDefault();
			$(this).css('opacity', '0.6');
		});

		// Rather click and select?
		obj.find('#attachment_click').change(function(e) {
			e.preventDefault();
			var files = $(this)[0].files;
			dragDropAttachment.prototype.handleFileUpload(files, obj);
			this.value = null;
		});
	});

	this.dragDropAttachment = dragDropAttachment;
}());
