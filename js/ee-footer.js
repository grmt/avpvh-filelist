// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

// Used in front-side and back-side file list display

console.log('ee-footer.js Loaded');

var eeSFL_isTouchscreen; // Declared in ee-head.js

// Upon page load completion...
jQuery(document).ready(function() {

	console.log('eeSFL Document Ready');

	jQuery('.eeSFL_BulkDownloadBar').hide();

	window.addEventListener('touchstart', function() {
		eeSFL_isTouchscreen = true;
	});


	jQuery('.eeSFL_ModalClose').on('click', function() {
		jQuery('.eeSFL_Modal').hide();
	});


	// The File Operations Bar -----------------

	// Get the initial selected action
	var eeSFL_InitialAction = jQuery('#eeSFL_FileOpsAction').val();

	// Get translated text items
	var eeSFL_ZipFileName = jQuery('#eeSFL_ZipFileName').text();
	var eeSFL_DeleteText = jQuery('#eeSFL_DeleteText').text();
	var eeSFL_DescriptionPlaceholder = jQuery('#eeSFL_DescriptionPlaceholder').html();

	// Set the initial placeholder based on what's selected
	if(eeSFL_InitialAction == 'Description') {
		jQuery('#eeSFL_FileOpsActionInput').attr('placeholder', eeSFL_DescriptionPlaceholder);
	}

	// Required Inputs per Action
	jQuery('#eeSFL_FileOpsAction').on('change', function() {

		if(jQuery(this).val() == 'Delete') {

			console.log('Deleting Files');

			jQuery('#eeSFL_FileOpsActionInput').show();
			jQuery('#eeSFL_FileOpsActionInput').attr('name', 'eeSFL_DeletingFiles');
			jQuery('#eeSFL_FileOpsActionInput').attr('value', '');
			jQuery('#eeSFL_FileOpsActionInput').attr('placeholder', eeSFL_DeleteText);
			jQuery('#eeSFL_FileOpsActionInput').attr('disabled', 'disabled');
			jQuery('#eeSFL_FileOpsActionInput').removeAttr('required');

		} else if(jQuery(this).val() == 'Description') {

			console.log('Adding Description');

			jQuery('#eeSFL_FileOpsActionInput').show();
			jQuery('#eeSFL_FileOpsActionInput').attr('name', 'eeSFL_Description');
			jQuery('#eeSFL_FileOpsActionInput').attr('value', '');
			jQuery('#eeSFL_FileOpsActionInput').attr('placeholder', eeSFL_DescriptionPlaceholder);
			jQuery('#eeSFL_FileOpsActionInput').attr('required', 'required');
			jQuery('#eeSFL_FileOpsActionInput').removeAttr('disabled');

		} else if(jQuery(this).val() == 'Download') {

			console.log('Downloading Files');

			jQuery('#eeSFL_FileOpsActionInput').show();
			jQuery('#eeSFL_FileOpsActionInput').attr('name', 'eeSFL_ZipFileName');
			jQuery('#eeSFL_FileOpsActionInput').attr('value', eeSFL_ZipFileName + '.zip');
			jQuery('#eeSFL_FileOpsActionInput').attr('required', 'required');
			jQuery('#eeSFL_FileOpsActionInput').removeAttr('disabled');

		}

	});

	// Bulk Editing Checkboxes

	// Check / Uncheck All
	jQuery('#eeSFL_BulkEditAll').on('click', function() {

		if( ! jQuery('.eeSFL_BulkDownloadBar').is(":visible") ) {
			jQuery('.eeSFL_BulkDownloadBar').slideDown();
		}

		var eeSFL_FileOpsFiles = '';

		if(jQuery('#eeSFL_BulkEditAll').is(':checked')) {

			// console.log('Checking all ...');
			jQuery('.eeSFL_BulkEditCheck').prop('checked', jQuery(this).prop('checked'));


			// Loop through all checkboxes
			jQuery('.eeSFL_BulkEditCheck').each(function () {

				eeSFL_FileOpsFiles += ',' + jQuery(this).val();
			});

		} else {

			// console.log('Unchecking all ...');
			jQuery('.eeSFL_BulkEditCheck').removeAttr('checked');



			jQuery('.eeSFL_BulkDownloadBar').slideUp();
		}

		jQuery('#eeSFL_FileOpsFiles').val(eeSFL_FileOpsFiles); // Fill the hidden input
	});





	// Add Files to Bulk Edit List
	jQuery('.eeSFL_BulkEditCheck').on('click', function() {

		var eeSFL_BulkFileID = jQuery(this).val(); // This checkbox
		var eeSFL_FileOpsFiles = jQuery('#eeSFL_FileOpsFiles').val(); // The files we're working with

		if(eeSFL_BulkFileID) {

			if(jQuery('#eeSFL_BulkEdit_' + eeSFL_BulkFileID).is(':checked') ) {

				console.log('Bulk Edit File ID ADD: ' + eeSFL_BulkFileID);

				if( ! jQuery('.eeSFL_BulkDownloadBar').is(":visible") ) {
					jQuery('.eeSFL_BulkDownloadBar').slideDown();
				}

				eeSFL_FileOpsFiles = eeSFL_FileOpsFiles + ',' + eeSFL_BulkFileID;

			} else {

				console.log('Bulk Edit File ID REMOVE: ' + eeSFL_BulkFileID);

				eeSFL_FileOpsFiles = eeSFL_FileOpsFiles.replace(',' + eeSFL_BulkFileID, ''); // Remove this ID

				if( ! jQuery('.eeSFL_BulkEditCheck').is(':checked') ) {
					jQuery('.eeSFL_BulkDownloadBar').slideUp();
				}
			}
		}

		jQuery('#eeSFL_FileOpsFiles').val(eeSFL_FileOpsFiles);
		console.log('#eeSFL_FileOpsFiles = ' + eeSFL_FileOpsFiles);

	});

}); // END Ready Function


// Strip Slashes
String.prototype.eeSFL_StripSlashes = function(){
    return this.replace(/\\(.)/mg, "$1");
}


// Copy File URL to Clipboard
function eeSFL_CopyLinkToClipboard(eeSFL_FileURL) {

	var eeTemp = jQuery('<input name="eeTemp" type="url" class="" id="eeTemp" />').val(eeSFL_FileURL); // Create a temporary input
	jQuery("body").append(eeTemp); // Add it to the bottom of the page

	var eeTempInput = jQuery('#eeTemp');
	eeTempInput.focus();
	eeTempInput.select(); // Select the temp input
	// eeTempInput.setSelectionRange(0, 99999); /* For mobile devices <<<------------ TO DO */
	document.execCommand("copy"); // Copy to clipboard
	eeTemp.remove(); // Remove the temp input

    alert(eesfl_vars['eeCopyLinkText'] + "\r\n" + eeSFL_FileURL); // Alert the user
}





function eeSFL_EditFile(eeSFL_FileID) {

	event.preventDefault(); // Don't follow the link

	if( jQuery('#eeSFL_EditFileWrap_' + eeSFL_FileID).is(':visible') ) {

		jQuery('#eeSFL_EditFileWrap_' + eeSFL_FileID).slideUp();
		jQuery('#eeSFL_EditFile_' + eeSFL_FileID).text(eesfl_vars['eeEditText']);

	} else {

		jQuery('#eeSFL_EditFileWrap_' + eeSFL_FileID).slideDown();
		jQuery('#eeSFL_EditFile_' + eeSFL_FileID).text(eesfl_vars['eeCancelText']);
	}
}




// Triggered when you click the Delete link
function eeSFL_Delete(eeSFL_FileID) {

	event.preventDefault(); // Don't follow the link

	console.log('Deleting File ID #' + eeSFL_FileID);

	// Get the File Name
    var eeSFL_FileName = jQuery('#eeSFL_RowID-' + eeSFL_FileID + ' .eeSFL_RealFileName').text();

    console.log(eeSFL_FileName);

	if( confirm( eesfl_vars['eeConfirmDeleteText'] + "\r\n\r\n" + eeSFL_FileName ) ) {

		eeSFL_FileAction(eeSFL_FileID, 'Delete');

	}

}





function eeSFL_ValidateEmail(eeSFL_CheckEmail) {

	var eeSFL_EmailFormat = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

	if (eeSFL_CheckEmail.match(eeSFL_EmailFormat)) {
    	return 'GOOD';
  	} else {
	  	return "BAD";
  	}
}



// File Size Formatting
function eeSFL_GetFileSize(bytes, si) {

    var thresh = si ? 1000 : 1024;

    if(Math.abs(bytes) < thresh) {
        return bytes + ' B';
    }

    var units = si
        ? ['kB','MB','GB','TB','PB','EB','ZB','YB']
        : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
    var u = -1;

    do {
        bytes /= thresh;
        ++u;
    } while(Math.abs(bytes) >= thresh && u < units.length - 1);

    return bytes.toFixed(1)+' '+units[u];
}
