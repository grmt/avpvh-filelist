// Simple File List - Copyright 2026
// Author: Mitchell Bennis | support@simplefilelist.com | https://simplefilelist.com
// License: GPLv2 or later | https://www.gnu.org/licenses/gpl-2.0.html

// console.log('ee-edit-file.js v7 Loaded');

// Shared with ee-head.js / ee-back.js and the inline list scripts
var eeSFL_FileID, eeSFL_ListID, eeSFL_FileDateAdded, eeSFL_FileDateChanged;

// Carried from the edit modal to the save and AJAX handlers
var eeSFL_FileNameOld, eeSFL_FileNiceNameOld, eeSFL_FileDescriptionOld;
var eeSFL_FileDateAddedYearOld, eeSFL_FileDateAddedMonthOld, eeSFL_FileDateAddedDayOld;
var eeSFL_FileDateChangedYearOld, eeSFL_FileDateChangedMonthOld, eeSFL_FileDateChangedDayOld;
var eeSFL_FileNameNew, eeSFL_FileNiceNameNew, eeSFL_FileDescriptionNew;
var eeSFL_FileDateAddedNew, eeSFL_FileDateChangedNew;

// Delete Click Handler
function eeSFL_DeleteFile(eeSFL_FileID) {

	event.preventDefault(); // Don't follow the link

	console.log('Deleting File: ' + eeSFL_FileID);


	// Get the File Name
    var eeSFL_FileName = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' .eeSFL_RealFileName').text();

    console.log(eeSFL_FileName);

	if( confirm( eesfl_vars['eeConfirmDeleteText'] + "\r\n\r\n" + eeSFL_FileName ) ) {

		eeSFL_EditFileAction(eeSFL_FileID, 'Delete');

	}
}




// Edit Button Handler
function eeSFL_OpenEditModal(eeSFL_FileID) {

	event.preventDefault(); // Don't follow the link

	console.log('Editing File: ' + eeSFL_FileID);


	var eeSFL_RealFileName = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_RealFileName').text();
	jQuery('#eeSFL_Modal_EditFile .eeSFL_ModalFilePath').text(eeSFL_RealFileName);

	jQuery('#eeSFL_Modal_EditFile').show();

	// Clear these because the last chosen file info might show here
	jQuery('#eeSFL_FileNiceNameNew').val('');
	jQuery('#eeSFL_FileDescriptionNew').text('');
	jQuery('#eeSFL_FileDescriptionNew').val('');

	// Pre-Populate the Modal
	jQuery('#eeSFL_Modal_FileID').text(eeSFL_FileID);

	eeSFL_FileNameOld = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_RealFileName').text();
	jQuery('#eeSFL_FileNameNew').val(eeSFL_FileNameOld);

	eeSFL_FileNiceNameOld = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileNiceName').text();
	eeSFL_FileNiceNameOld = eeSFL_FileNiceNameOld.eeSFL_StripSlashes();
	jQuery('#eeSFL_FileNiceNameNew').val(eeSFL_FileNiceNameOld);

	eeSFL_FileDescriptionOld = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' .eeSFL_FileDesc').text();
	jQuery('#eeSFL_FileDescriptionNew').val(eeSFL_FileDescriptionOld);

	var eeSFL_FileSize = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileSize').text();
	jQuery('#eeSFL_FileSize').text(eeSFL_FileSize);

	// Dates - Icky

	eeSFL_FileDateAdded = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileDateAddedNice').text();
	jQuery('#eeSFL_FileDateAdded').text(eeSFL_FileDateAdded);
	eeSFL_FileDateAdded = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileDateAdded').text();

	var eeArrayDateAdded = eeSFL_FileDateAdded.split('-');
	eeSFL_FileDateAddedYearOld = eeArrayDateAdded[0];
	eeSFL_FileDateAddedMonthOld = eeArrayDateAdded[1];
	eeSFL_FileDateAddedDayOld = eeArrayDateAdded[2];
	jQuery('#eeSFL_FileDateAddedYearNew').val(eeSFL_FileDateAddedYearOld);
	jQuery('#eeSFL_FileDateAddedMonthNew').val(eeSFL_FileDateAddedMonthOld);
	jQuery('#eeSFL_FileDateAddedDayNew').val(eeSFL_FileDateAddedDayOld);


	eeSFL_FileDateChanged = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileDateChangedNice').text();
	jQuery('#eeSFL_FileDateChanged').text(eeSFL_FileDateChanged);
	eeSFL_FileDateChanged = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileDateChanged').text();

	var eeArrayDateChanged = eeSFL_FileDateChanged.split('-');
	eeSFL_FileDateChangedYearOld = eeArrayDateChanged[0];
	eeSFL_FileDateChangedMonthOld = eeArrayDateChanged[1];
	eeSFL_FileDateChangedDayOld = eeArrayDateChanged[2];
	jQuery('#eeSFL_FileDateChangedYearNew').val(eeSFL_FileDateChangedYearOld);
	jQuery('#eeSFL_FileDateChangedMonthNew').val(eeSFL_FileDateChangedMonthOld);
	jQuery('#eeSFL_FileDateChangedDayNew').val(eeSFL_FileDateChangedDayOld);



}





// Modal Form Has Been Saved
function eeSFL_FileEditSaved() {

	var eeChanged = false;

	eeSFL_FileID = jQuery('#eeSFL_Modal_FileID').text();

	// Get Modal Form Inputs and Set Variables
	eeSFL_FileNameNew = jQuery('#eeSFL_FileNameNew').val();

	if(eeSFL_FileNameNew.length < 1) {

		eeSFL_FileNameNew = false;

	} else if(eeSFL_FileNameOld == eeSFL_FileNameNew) {

		eeSFL_FileNameNew = false;

	} else {

		// Sanitize File Name
		eeSFL_FileNameNew = eeSFL_FileNameNew.replace(/ /g, '-'); // Deal with spaces
		eeSFL_FileNameNew = eeSFL_FileNameNew.replace(/--/g, '-'); // Deal with double dash

		if(eeSFL_FileNameOld.indexOf('.') > 1) { // It's a File

			// Disallow removing extension
			if(eeSFL_FileNameNew.indexOf('.') == -1) { return false; }

			// Remove dots from name-part
			var eeArray = eeSFL_FileNameNew.split('.');

			if(eeArray.length > 2) {

				var eeExt = eeArray.pop();
				var eeFileNameNew = '';

				for(var i = 0; i < eeArray.length; i++) {
					eeFileNameNew += eeArray[i] + '-'; // Rebuild using dashes
				}
				eeFileNameNew = eeFileNameNew.substring(0, eeFileNameNew.length - 1); // Strip last dash

				eeSFL_FileNameNew = eeFileNameNew + '.' + eeExt;
			}

		} else { // A name without an extension keeps none

			// Disallow removing extension
			if(eeSFL_FileNameNew.indexOf('.') > 1) { return false; } // Can't add dots

		}

		eeChanged = true;
	}

	eeSFL_FileNiceNameNew = jQuery('#eeSFL_FileNiceNameNew').val();
	if(eeSFL_FileNiceNameOld != eeSFL_FileNiceNameNew) { // We'll trim later to remove this completely
		if(eeSFL_FileNiceNameNew.length < 1 ) { eeSFL_FileNiceNameNew = ''; }
		eeChanged = true;
	} else {
		eeSFL_FileNiceNameNew = false;
	}

	eeSFL_FileDescriptionNew = jQuery('#eeSFL_FileDescriptionNew').val();
	if(eeSFL_FileDescriptionOld != eeSFL_FileDescriptionNew) {
		if(eeSFL_FileDescriptionNew.length < 1 ) { eeSFL_FileDescriptionNew = ''; } // We'll trim later to remove this completely
		eeChanged = true;
	} else {
		eeSFL_FileDescriptionNew = false;
	}

	// Dates - What a PAIN! (Needs Improvement)

	var eeSFL_FileDateAddedYearNew = jQuery('#eeSFL_FileDateAddedYearNew').val();
	if(eeSFL_FileDateAddedYearOld == eeSFL_FileDateAddedYearNew) { eeSFL_FileDateAddedYearNew = false; } else { eeChanged = true; }

	var eeSFL_FileDateAddedMonthNew = jQuery('#eeSFL_FileDateAddedMonthNew').val();
	if(eeSFL_FileDateAddedMonthOld == eeSFL_FileDateAddedMonthNew) { eeSFL_FileDateAddedMonthNew = false; } else { eeChanged = true; }

	var eeSFL_FileDateAddedDayNew = jQuery('#eeSFL_FileDateAddedDayNew').val();
	if(eeSFL_FileDateAddedDayOld == eeSFL_FileDateAddedDayNew) { eeSFL_FileDateAddedDayNew = false; } else { eeChanged = true; }

	var eeSFL_FileDateAddedYear, eeSFL_FileDateAddedMonth, eeSFL_FileDateAddedDay;
	if(eeSFL_FileDateAddedYearNew != false || eeSFL_FileDateAddedMonthNew != false || eeSFL_FileDateAddedDayNew != false) {
		if(eeSFL_FileDateAddedYearNew == false) { eeSFL_FileDateAddedYear = eeSFL_FileDateAddedYearOld; } else { eeSFL_FileDateAddedYear = eeSFL_FileDateAddedYearNew; }
		if(eeSFL_FileDateAddedMonthNew == false) { eeSFL_FileDateAddedMonth = eeSFL_FileDateAddedMonthOld; } else { eeSFL_FileDateAddedMonth = eeSFL_FileDateAddedMonthNew; }
		if(eeSFL_FileDateAddedDayNew == false) { eeSFL_FileDateAddedDay = eeSFL_FileDateAddedDayOld; } else { eeSFL_FileDateAddedDay = eeSFL_FileDateAddedDayNew; }
		eeSFL_FileDateAddedNew = eeSFL_FileDateAddedYear + '-' + eeSFL_FileDateAddedMonth + '-' + eeSFL_FileDateAddedDay;
	} else {
		eeSFL_FileDateAddedNew = '';
	}

	var eeSFL_FileDateChangedYearNew = jQuery('#eeSFL_FileDateChangedYearNew').val();
	if(eeSFL_FileDateChangedYearOld == eeSFL_FileDateChangedYearNew) { eeSFL_FileDateChangedYearNew = false; } else { eeChanged = true; }

	var eeSFL_FileDateChangedMonthNew = jQuery('#eeSFL_FileDateChangedMonthNew').val();
	if(eeSFL_FileDateChangedMonthOld == eeSFL_FileDateChangedMonthNew) { eeSFL_FileDateChangedMonthNew = false; } else { eeChanged = true; }

	var eeSFL_FileDateChangedDayNew = jQuery('#eeSFL_FileDateChangedDayNew').val();
	if(eeSFL_FileDateChangedDayOld == eeSFL_FileDateChangedDayNew) { eeSFL_FileDateChangedDayNew = false; } else { eeChanged = true; }

	var eeSFL_FileDateChangedYear, eeSFL_FileDateChangedMonth, eeSFL_FileDateChangedDay;
	if(eeSFL_FileDateChangedYearNew != false || eeSFL_FileDateChangedMonthNew != false || eeSFL_FileDateChangedDayNew != false) {
		if(eeSFL_FileDateChangedYearNew == false) { eeSFL_FileDateChangedYear = eeSFL_FileDateChangedYearOld; } else { eeSFL_FileDateChangedYear = eeSFL_FileDateChangedYearNew; }
		if(eeSFL_FileDateChangedMonthNew == false) { eeSFL_FileDateChangedMonth = eeSFL_FileDateChangedMonthOld; } else { eeSFL_FileDateChangedMonth = eeSFL_FileDateChangedMonthNew; }
		if(eeSFL_FileDateChangedDayNew == false) { eeSFL_FileDateChangedDay = eeSFL_FileDateChangedDayOld; } else { eeSFL_FileDateChangedDay = eeSFL_FileDateChangedDayNew; }
		eeSFL_FileDateChangedNew = eeSFL_FileDateChangedYear + '-' + eeSFL_FileDateChangedMonth + '-' + eeSFL_FileDateChangedDay;
	} else {
		eeSFL_FileDateChangedNew = '';
	}


	if( eeChanged === true ) {

		console.log('Saving Edits to File: ' + eeSFL_FileID);

		if(eeSFL_FileNameNew == false) { eeSFL_FileNameNew = ''; } // Otherwise a file could not be named "false"

		eeSFL_EditFileAction(eeSFL_FileID, 'Edit');

	} else {

		console.log('Nothing Has Changed');
	}

	// Hide the Modal Overlay
	jQuery('.eeSFL_Modal').hide();
}






function eeSFL_EditFileAction(eeSFL_FileID, eeSFL_FileAction) {

	event.preventDefault(); // Don't follow link

	eeSFL_ListID = jQuery('#eeSFL_ID').text();

	console.log('List ID = ' + eeSFL_ListID);
	console.log(eeSFL_FileAction + ' -> ' + eeSFL_FileID);

	// The File Action Engine
	var eeActionEngine = eesfl_vars.ajaxurl;
	var eeFormData = false;
	var eeSFL_ActionNonce = jQuery('#eeSFL_ActionNonce').text(); // Get the Nonce


	if(eeSFL_FileAction == 'Edit') {

		eeFormData = {
			'action': 'simplefilelist_edit_job',
			'eeSFL_ID': eeSFL_ListID,
			'eeFileName': eeSFL_FileNameOld,
			'eeFileNameNew': eeSFL_FileNameNew,
			'eeFileNiceNameNew': eeSFL_FileNiceNameNew,
			'eeFileDescNew': eeSFL_FileDescriptionNew,
			'eeFileDateAdded': eeSFL_FileDateAddedNew,
			'eeFileDateChanged': eeSFL_FileDateChangedNew,
			'eeFileAction': 'Edit',
			'eeSecurity': eeSFL_ActionNonce
		};

	} else if(eeSFL_FileAction == 'Delete') {

		// Get the File Name
		var eeSFL_FileName = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_RealFileName').text();

		eeFormData = {
			'action': 'simplefilelist_edit_job',
			'eeSFL_ID': eeSFL_ListID,
			'eeFileName': eeSFL_FileName,
			'eeFileAction': 'Delete',
			'eeSecurity': eeSFL_ActionNonce
		};

	}

	console.log(eeFormData);


	// AJAX
	console.log('Calling: ' + eeActionEngine);

	jQuery.post(eeActionEngine, eeFormData, function(response) {

		if(response.indexOf('SUCCESS') == 0) { // :-)

			if(eeSFL_FileAction == 'Edit') {

				// Update the Display

				var eeFileNameDisplay = eeSFL_FileNameOld;
				var eeFileNameActual = eeSFL_FileNameOld;


				if(eeSFL_FileNameNew) {

					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_RealFileName').text(eeSFL_FileNameNew);

					console.log('File: ' + eeSFL_FileNameOld);
					console.log('Renamed To: ' + eeSFL_FileNameNew);

					// Update hrefs by swapping the old filename for the new one in the existing URL.
					// This preserves the full URL format including site URL, subfolder, and any query params (e.g. SFLA ee-get-file URLs).
					var eeOldFileURL = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileName').attr('href');
					if(eeOldFileURL) { jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileName').attr('href', eeOldFileURL.replace(eeSFL_FileNameOld, eeSFL_FileNameNew)); }

					var eeOldThumbURL = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' td.eeSFL_Thumbnail a').attr('href');
					if(eeOldThumbURL) { jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' td.eeSFL_Thumbnail a').attr('href', eeOldThumbURL.replace(eeSFL_FileNameOld, eeSFL_FileNameNew)); }

					var eeOldOpenURL = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileOpen').attr('href');
					if(eeOldOpenURL) { jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileOpen').attr('href', eeOldOpenURL.replace(eeSFL_FileNameOld, eeSFL_FileNameNew)); }

					var eeOldDownloadURL = jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileDownload').attr('href');
					if(eeOldDownloadURL) { jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileDownload').attr('href', eeOldDownloadURL.replace(eeSFL_FileNameOld, eeSFL_FileNameNew)); }

				

					eeFileNameDisplay = eeSFL_FileNameNew;
					eeFileNameActual = eeSFL_FileNameNew;

					// No nice name — update the displayed file name
					if( !eeSFL_FileNiceNameOld || eeSFL_FileNiceNameOld === eeSFL_FileNameOld ) {
						jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileName').text(eeSFL_FileNameNew);
					}

				}




				if( eeSFL_FileNiceNameNew !== false ) {

					console.log('Changed Nice Name');

					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileNiceName').text(eeSFL_FileNiceNameNew);
					var eeNiceNameDisplay = eeSFL_FileNiceNameNew.length >= 1 ? eeSFL_FileNiceNameNew : eeFileNameActual;
					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' a.eeSFL_FileName').text(eeNiceNameDisplay);

				}


				if(eeSFL_FileDescriptionNew) {

					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' p.eeSFL_FileDesc').removeClass('eeHide');
					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' p.eeSFL_FileDesc').text(eeSFL_FileDescriptionNew);
					eeSFL_FileDescriptionOld = '';

				} else if(eeSFL_FileDescriptionNew === '') {

					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' p.eeSFL_FileDesc').addClass('eeHide');
					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' p.eeSFL_FileDesc').text('');
					jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' .eeSFL_FileDesc').text('');
					eeSFL_FileDescriptionOld = '';
				}


				// Check for a formatted date in the response
				if(response.indexOf('|') > 1) {

					var eeArray = response.split('|');
					var eeArray2 = eeArray[1].split('=');
					if(eeArray2[0] == 'Date') {
						jQuery('#eeSFL_FileID-' + eeSFL_FileID + ' span.eeSFL_FileDateDisplayed').text(eeArray2[1]);
					}
				}


				eeSFL_FileDescriptionOld = '';
				eeSFL_FileNiceNameOld = '';



			} else if(eeSFL_FileAction == 'Delete') {

				jQuery('#eeSFL_FileID-' + eeSFL_FileID).hide('slow');

			}

		} else { // NOT SUCCESS :-(

			alert(response);
		}

		console.log(response);


	});
}


