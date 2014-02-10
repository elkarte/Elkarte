/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0 Beta 2
 *
 * This file contains javascript associated with the drag drop of files functionality
 * while posting
 */

/**
 * Simply invoke the constructor by calling dragDropAttachment
 *
 */
(function() {
	function dragDropAttachment() {}

	dragDropAttachment = function() {
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

			/**
			 * public function, accisible with prototype chain
			 * @param {object} params
			 * allowedExtensions - types of attachments allowed
			 * totalSizeAllowed - maximum size of total attachments allowed
			 * individualSizeAllowed - maximum individual file size allowed
			 * numOfAttachmentAllowed - number of files that can be attached in a post
			 * totalAttachSizeUploaded - total size of already attached files(modifying post)
			 * numAttachUploaded - number of already attached files(modifying post)
			 */
			init = function(params) {
				allowedExtensions = (params.allowedExtensions === '') ? [] : params.allowedExtensions.replace(/\s/g, '').split(',');
				totalSizeAllowed = (params.totalSizeAllowed === '') ? null : params.totalSizeAllowed;
				individualSizeAllowed = (params.individualSizeAllowed === '') ? null : params.individualSizeAllowed;
				numOfAttachmentAllowed = (params.numOfAttachmentAllowed === '') ? null : params.numOfAttachmentAllowed;
				totalAttachSizeUploaded = params.totalAttachSizeUploaded / 1024;
				numAttachUploaded = params.numAttachUploaded;
				filesUploadedSuccessfully = [];
			},

			/**
			 * private function
			 * @param formdata, current instance of upload UI
			 * formData - current file with data to upload
			 * status - current progress bar UI instance
			 * upload the file to server and update the UI
			 */
			sendFileToServer = function(formData, status) {
				var jqXHR = $.ajax({
					xhr: function() {
						var xhrobj = $.ajaxSettings.xhr();
						if (xhrobj.upload) {
							xhrobj.upload.addEventListener('progress', function(event) {
								var percent = 0;
								var position = event.loaded || event.position;
								var total = event.total;
								if (event.lengthComputable) {
									percent = Math.ceil(position / total * 100);
								}
								status.setProgress(percent);
							}, false);
						}
						return xhrobj;
					},
					url: elk_scripturl + '?action=attachment;sa=ulattach;' + elk_session_var + '=' + elk_session_id,
					type: "POST",
					contentType: false,
					processData: false,
					cache: false,
					data: formData,
				})
				.done(function(resp) {
						if (typeof(resp) !== 'object') resp = JSON.parse(resp);

						if (resp.result) {
							status.setProgress(100);
							var curFileNum = filesUploadedSuccessfully.length,
								data = resp.data;

							filesUploadedSuccessfully.push(data);
							data.curFileNum = curFileNum;
							status.onUploadSuccess(data);
						} else {
							status.setProgress(0);
						}
				})
				.fail(function(error) {
						console.log('error');
						console.log(error);
				})
				.always(function() {
						uploadInProgress = false;
						runAttachmentQueue();
				});
				status.setAbort(jqXHR);
			};

			/**
			 * private function
			 * @param {object} options
			 * removes the file to server
			 */
			removeFileFromServer = function(options) {
				var dataToSend = filesUploadedSuccessfully[options.fileNum];
				$.ajax({
					url: elk_scripturl + '?action=attachment;sa=rmattach;' + elk_session_var + '=' + elk_session_id,
					type: "POST",
					cache: false,
					dataType: 'json',
					data: {
						'filename': dataToSend.temp_name,
						'filepath': dataToSend.temp_path,
					}
				})
				.done(function(resp) {
						if (typeof(resp) !== 'object') resp = JSON.parse(resp);

						if (resp.result) {
							totalAttachSizeUploaded -= filesUploadedSuccessfully[options.fileNum].size / 1024;
							numAttachUploaded--;
							$('#' + dataToSend.temp_name).unbind();
							$('#' + dataToSend.temp_name).remove();
						} else {
							console.log('error success');
						}
				})
				.fail(function(error) {
						console.log('error');
				});
			};

			/**
			 * private function
			 * @param {object} options
			 * creates the UI for each file
			 */
			createStatusbar = function(obj) {
				this.str = $('<div class="statusbar"><div class="info"></div><div class="progressBar"><div></div></div><div class="abort">X</div></div>');

				$('.progress_tracker').append(this.str);
				this.setFileNameSize = function(name, size) {
					var sizeStr = "";
					var sizeKB = size / 1024;

					if (parseInt(sizeKB, 10) > 1024) {
						var sizeMB = sizeKB / 1024;
						sizeStr = sizeMB.toFixed(2) + " MB";
					} else {
						sizeStr = sizeKB.toFixed(2) + " KB";
					}

					$(this.str).find('.info').html(name + ' (' + sizeStr + ')');
				};

				this.setProgress = function(progress) {
					var progressBarWidth = progress * $(this.str).find('.progressBar').width() / 100;
					$(this.str).find('.progressBar div').animate({
						width: progressBarWidth
					}, 10).html(progress + "% ");
				};

				this.setAbort = function(jqxhr) {
					var sb = $(this.str);
					$(this.str).find('.abort').bind('click', function(e) {
						e.preventDefault();
						jqxhr.abort();
						sb.hide();
					});
				};

				this.onUploadSuccess = function(data) {
					$(this.str).find('.abort').unbind('click');
					$(this.str).find('.abort').removeClass('abort').addClass('remove');
					$(this.str).find('.remove').attr('id', data.curFileNum);
					$(this.str).attr('id', data.temp_name);
					$(this.str).attr('data-size', data.size);

					// $(this.str).find('.progressBar').fadeOut(500);
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
			 * @param files and region of UI
			 * handle the functionality when file is dropped
			 * files - what files to upload
			 * obj - parent object in which file progress is shown
			 */
			handleFileUpload = function(files, obj) {
				var errorMsgs = {},
					extnErrorFiles = [],
					sizeErrorFiles = [];

				for (var i = 0; i < files.length; i++) {
					var fileExtensionCheck = /(?:\.([^.]+))?$/,
						extension = fileExtensionCheck.exec(files[i].name)[1],
						fileSize = files[i].size / 1024,
						errorFlag = false;

					if (allowedExtensions.length > 0 && allowedExtensions.indexOf(extension) < 0) {
						errorMsgs.extnError = 'File extension not allowed';
						extnErrorFiles.push(files[i].name);
						errorFlag = true;
					}
					if (individualSizeAllowed !== null && fileSize > individualSizeAllowed) {
						errorMsgs.individualSizeErr = 'File size too big';
						sizeErrorFiles.push(files[i].name);
						errorFlag = true;
					}

					if (numAttachUploaded >= numOfAttachmentAllowed) {
						errorMsgs.maxNumErr = 'Sorry, you aren\'t allowed to post any more attachments.';
						sizeErrorFiles.push(files[i].name);
						errorFlag = true;
					}

					if (errorFlag === false) totalAttachSizeUploaded += fileSize;

					if (totalSizeAllowed !== null && totalAttachSizeUploaded > totalSizeAllowed) {
						errorMsgs.totalSizeError = 'Maximum file size reached';
						errorFlag = true;
					}

					if (errorFlag === false) {
						numAttachUploaded++;

						var fd = new FormData();
						fd.append('attachment[]', files[i]);
						var status = new createStatusbar(obj);
						status.setFileNameSize(files[i].name, files[i].size);
						attachmentQueue.push({
							'formData': fd,
							'statusInstance': status
						});
					}
				}
				populateErrors({
					'errorMsgs': errorMsgs,
					'extnErrorFiles': extnErrorFiles,
					'sizeErrorFiles': sizeErrorFiles
				});
				runAttachmentQueue();
			},

			/**
			 * private function
			 * checks if there is an file pending to upload
			 */
			runAttachmentQueue = function() {
				if (attachmentQueue.length > 0 && uploadInProgress === false) {
					uploadInProgress = true;
					var currentData = attachmentQueue[0];
					sendFileToServer(currentData.formData, currentData.statusInstance);
					attachmentQueue.splice(0, 1);
				}
			},

			/**
			 * private function
			 * @param {object} params
			 * error messages to show
			 * file names having extension error
			 * file names having size error
			 */
			populateErrors = function(params) {
				$('.drop_attachments_error').html('');

				for (var err in params.errorMsgs) {
					if (params.errorMsgs.hasOwnProperty(err)) {
						var errorMsg = '';
						switch (err) {
							case 'extnError':
								errorMsg = '<p class="warningbox">' + params.errorMsgs[err] + '<br/ ><span>' + params.extnErrorFiles.join(', ') + '</span></p>';
								break;

							case 'individualSizeErr':
								errorMsg = '<p class="warningbox">' + params.errorMsgs[err] + '<br/ ><span>' + params.sizeErrorFiles.join(', ') + '</span></p>';
								break;

							default:
								errorMsg = '<p class="warningbox">' + params.errorMsgs[err] + '</p>';
								break;
						}
						$('.drop_attachments_error').append(errorMsg);
					}
				}
			},

			/**
			 * private function
			 * @param string
			 * to check if value exist in array
			 */
			indexOf = function(needle) {
				if (typeof Array.prototype.indexOf === 'function') {
					indexOf = Array.prototype.indexOf;
				} else {
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
			handleFileUpload: handleFileUpload,
		};
	}();

	$(document).ready(function() {
		var obj = $(".drop_area");

		obj.on('dragenter', function(e) {
			e.stopPropagation();
			e.preventDefault();
			$(this).css('border', 'solid 1px rgb(64, 118, 182)');
		});

		obj.on('dragover', function(e) {
			e.stopPropagation();
			e.preventDefault();
		});

		obj.on('drop', function(e) {
			e.preventDefault();
			$(this).css('border', 'solid 1px rgb(64, 118, 182)');
			var files = e.originalEvent.dataTransfer.files;
			dragDropAttachment.handleFileUpload(files, obj);
		});
	});
	this.dragDropAttachment = dragDropAttachment;
}());
