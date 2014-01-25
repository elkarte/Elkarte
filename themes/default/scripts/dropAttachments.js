// https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/Using_XMLHttpRequest

var filesUploadedSuccessfully = [];
function sendFileToServer(formData, status) {
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
		url: elk_scripturl + '?action=attachment;sa=ulattach',
		type: "POST",
		contentType: false,
		processData: false,
		cache: false,
		data: formData,
		success: function(resp) {
			if(typeof(resp) !== 'object') resp = JSON.parse(resp);

			if (resp.result) {
				status.setProgress(100);
				var curFileNum = filesUploadedSuccessfully.length;
				filesUploadedSuccessfully.push(resp.data);
				var data = resp.data;
				data.curFileNum = curFileNum;
				status.onUploadSuccess(data);
			} else {
				status.setProgress(0);
			}
		},
		error: function(error) {
			console.log('error');
			console.log(error);
		}
	});
	status.setAbort(jqXHR);
}

function removeFileFromServer(options) {
	var dataToSend = filesUploadedSuccessfully[options.fileNum];
	console.log(dataToSend);
	$.ajax({
		url: elk_scripturl + '?action=attachment;sa=rmattach',
		type: "POST",
		cache: false,
		dataType: 'json',
		data: {
			'filename': dataToSend.temp_name,
			'filepath': dataToSend.temp_path,
		},
		success: function(resp) {
			if(typeof(resp) !== 'object') resp = JSON.parse(resp);

			if (resp.result) {
				console.log('success');
				$('#' + dataToSend.temp_name).unbind();
				$('#' + dataToSend.temp_name).remove();
			} else {
				console.log('error success');
			}
		},
		error: function(error) {
			console.log('error');
		}
	});
}

function createStatusbar(obj) {
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

		$(this.str).find('.progressBar').fadeOut(500);
		$(this.str).find('.remove').bind('click', function(e) {
			e.preventDefault();
			var fileNum = e.target.id;
			removeFileFromServer({
				'fileNum': fileNum
			});
		});
	};
}

function handleFileUpload(files, obj) {
	for (var i = 0; i < files.length; i++) {
		var fd = new FormData();
		fd.append('attachment[]', files[i]);
		var status = new createStatusbar(obj);
		status.setFileNameSize(files[i].name, files[i].size);
		sendFileToServer(fd, status);
	}
}

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
		handleFileUpload(files, obj);
	});
});