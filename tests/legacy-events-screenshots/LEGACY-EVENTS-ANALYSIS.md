# Análise do Sistema Legado de Eventos - English Australia

**Data da análise:** 2025-12-18T14:54:12.853Z
**URL do sistema:** https://www.englishaustralia.com.au/administration/eventsadmin/events

## Estrutura da Tabela de Eventos

| Coluna |
|--------|
| English Australia - Association Online Administration

						
						

							

								

								Logged in as: Rodrigo Zillesg  
								 
								 
								 
								

							

						
					
					

					
					
						

							
							
								
								

									
									Dashboard
									
								
								
								

									
									People
									
								
								
								

									
									Members
									
								
								
								

									
									Events
									
								
								
								

									
									Marketing
									
								
								
								

									
									Commerce
									
								
								
								

									
									Website CMS
									
								
								
								

									
									Reports
									
								
								
								

									
									Tools
									
								
								
							
							

						
					
					

				
			
			
				
				

					

					

					
						Event Management
					

					
						
					
						Events
					
							
						
					
						Reports
					
							
						
					
						Events Import
					
							
						
					
						Email Log
					
							
						
					
						Import/Export
					
							
						
					
						Setup
					
							
						
					

					

					
						Abstracts
					

					
						
					

					

					
						Goto Webinar
					

					
						
					

					

				
				

				

					
					
						

							
								events2.setSettings( {"ajaxPageUrl":"/administration/eventsadmin/events/ajax","editEventUrl":"/administration/eventsadmin/events/event"} ) 		
			
				
											
									
							Categories
						
							
				add_dom_javascript('/sb/modules/core/javascript/sb.js');
			
						
			var categoryToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function categoryToolbarUpdateToolbar( selections )
			{
				categoryToolbarSelections = selections;
								var btn = $("#categoryToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function categoryToolbarOnClick5()
					{
						var type = 5
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=addCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, categoryToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick1()
					{
						var type = 1
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=editCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick3()
					{
						var type = 3
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=deleteCategory&cid=0'}
						var onClickConfirmMessage = "Are you sure you want to delete the selected categories?";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function categoryToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = ""

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function categoryToolbarShowSaveMessage() {
				if ( document.getElementById("categoryToolbarSaveMessage") )
				{
					document.getElementById("categoryToolbarSaveMessage").style.display = 'block';
					window.setTimeout("categoryToolbarHideSaveMessage()", 1000);
				}
			}

			function categoryToolbarHideSaveMessage() {
				document.getElementById("categoryToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
					
			
				
				saved
			
						
				function loadTree() {
					$( "#tree" ).treeview( {
						persist: "location",
						collapsed: 1,
						unique:  false
					} );
				}
			
			
							
				Events
							
				English Australia Events
				
						
				Webinars
				
						
				Sector events 
				
						
				SIG Events
				
			
						
				Home Page Events
				
						
				Hidden From Public
				
						
				All
				
						
				Uncategorised
				
			
			
			
				loadTree();
						
		
								
													
								 
							
													
									
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
			var eventsListDataGridToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function eventsListDataGridToolbarUpdateToolbar( selections )
			{
				eventsListDataGridToolbarSelections = selections;
								var btn = $("#eventsListDataGridToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar4")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copy.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copyDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function eventsListDataGridToolbarOnClick5()
					{
						var type = 5
						var onClick = function () { events2.admin.events.add( -2 ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, eventsListDataGridToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick1()
					{
						var type = 1
						var onClick = ( function( value ) { events2.admin.events.edit( value ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick4()
					{
						var type = 4
						var onClick = ( function( value ) { events2.admin.events.copy( value, -2, "eventsListDataGrid" ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick3()
					{
						var type = 3
						var onClick = function ( value ) { events2.admin.events.deleteEvent( value, "eventsListDataGrid" ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function eventsListDataGridToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = "eventsListDataGrid"

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function eventsListDataGridToolbarShowSaveMessage() {
				if ( document.getElementById("eventsListDataGridToolbarSaveMessage") )
				{
					document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'block';
					window.setTimeout("eventsListDataGridToolbarHideSaveMessage()", 1000);
				}
			}

			function eventsListDataGridToolbarHideSaveMessage() {
				document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
										
					
			
				
				saved
			
							
					var eventsListDataGridTimer = 0;

					function eventsListDataGridStartTimer() {
						if (eventsListDataGridTimer > 0) {
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridIncrementTimer() {
						eventsListDataGridTimer += 5;

						if (eventsListDataGridTimer == 1000) {
							eventsListDataGridRunSearch();
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridRunSearch() {
						$.get('/administration/eventsadmin/events/sbHtml', { action: 'searchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: $('#eventsListDataGridSearchInput').val() },
								function(data)
								{
				    				eventsListDataGridSearchTerm = $('#eventsListDataGridSearchInput').val();
				    				eventsListDataGridRangeStart = 0;
				    				eventsListDataGridPageNumber = 1;
				    				eventsListDataGridRefreshList();
				    				
				  				}
				  				);					}

					function eventsListDataGridSearchOnEnter(e) {
						if (e.keyCode == 13) {
							eventsListDataGridRunSearch();
						}
					}
				
				Search			
						AllActiveExpiredUpcomingNot Public 			

				$(document).ready(function () {
					// create the loading window and set autoOpen to false
					$("#eventsListDataGridLoadingScreen").dialog({
						autoOpen: false,	// set this to false so we can manually open it
						dialogClass: "loadingScreenWindow",
						closeOnEscape: false,
						draggable: false,
						width: 460,
						minHeight: 50,
						modal: true,
						buttons: {},
						resizable: false,
						open: function () {
							// scrollbar fix for IE
							$('body').css('overflow', 'hidden');
						},
						close: function () {
							// reset overflow
							$('body').css('overflow', 'auto');
						}
					}); // end of dialog
				});

				function eventsListDataGridWaitingDialog(waiting) { // I choose to allow my loading screen dialog to be customizable, you don't have to
					var dialog = $("#eventsListDataGridLoadingScreen");

					dialog.html(waiting.message && '' != waiting.message ? waiting.message : 'Please wait...');
					dialog.dialog({ autoOpen: false })
					dialog.dialog('option', 'title', waiting.title && '' != waiting.title ? waiting.title : 'Loading');
					dialog.dialog('open');
				}

				function eventsListDataGridCloseWaitingDialog() {
					var dialog = $("#eventsListDataGridLoadingScreen");
					dialog.dialog({autoOpen: false})
					dialog.dialog('close');
					dialog.remove()
				}
			
						
			
			
									
						From					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeFrom";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeFromClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
					
						 
					
										
						To					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeTo";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeToClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
								
			
			

			

				SBHTML.add( new HtmlDateRange( 'eventsListDataGridDateRange', {} ) );

			
					
			
				
				
											
							 						
												
															Event Title													
												
															Start Date													
												
															Periods													
												
															Status													
												
															Places													
												
															Registrations to date													
										
				
				
							
									
								
							
					
				

				
						
							
									
						Managing classroom activities					
									
						11th Jun 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Teaching with What’s Real: Australian Content, and Practical ELICOS Materials					
									
						12th May 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Exploiting learning resources					
									
						8th Apr 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Making intensive reading work: Practical strategies for the English language classroom					
									
						11th Mar 26					
									
						1					
									
						Active					
									
											
									
						12					
							
						
									
								
							
					
				

				
						
							
									
						Teaching mixed-level classes					
									
						11th Feb 26					
									
						1					
									
						Active					
									
											
									
						15					
							
						
									
								
							
					
				

				
						
							
									
						A Taste of the English Australia Conference - Melbourne					
									
						29th Jan 26					
									
						1					
									
						Active					
									
											
									
						6					
							
						
									
								
							
					
				

				
						
							
									
						Online Member Meeting re Education Legislation Amendment Bill					
									
						5th Dec 25					
									
						1					
									
						Expired					
									
											
									
						123					
							
						
									
								
							
					
				

				
						
							
									
						Reassessing Assessment in EAP: A student perspective					
									
						2nd Dec 25					
									
						1					
									
						Expired					
									
											
									
						74					
							
						
									
								
							
					
				

				
						
							
									
						Vic Advisors Meeting					
									
						27th Nov 25					
									
						1					
									
						Expired					
									
											
									
						20					
							
						
									
								
							
					
				

				
						
							
									
						Using Custom GPTs in English language teaching and learning					
									
						27th Nov 25					
									
						1					
									
						Not Public					
									
											
									
						42					
							
						
									
								
							
					
				

				
						
							
									
						2025 Ed-Tech Symposium					
									
						12th Nov 25					
									
						1					
									
						Expired					
									
											
									
						149					
							
						
									
								
							
					
				

				
						
							
									
						AI Bots vs Boffins: How can we best assist our students?					
									
						5th Nov 25					
									
						1					
									
						Expired					
									
											
									
						103					
							
						
									
								
							
					
				

				
						
							
									
						Impacting and Inspiring Neurodiverse Learners					
									
						29th Oct 25					
									
						1					
									
						Expired					
									
											
									
						81					
							
						
									
								
							
					
				

				
						
							
									
						Building Custom GPTs					
									
						23rd Oct 25					
									
						1					
									
						Expired					
									
						130					
									
						130					
							
						
									
								
							
					
				

				
						
							
									
						Member Meeting: Changes to the English Australia Constitution					
									
						22nd Oct 25					
									
						1					
									
						Expired					
									
											
									
						18					
							
							
			
		
	[ 411 records ]1
2
3
4
5
6
7
8
9
10
 | 11 - 20
 | next >
 | last >>
[ 28 pages ]		15
			
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
				var eventsListDataGridRangeStart = '0';
				var eventsListDataGridAlphabet = '0';
				var eventsListDataGridPageNumber = '1';
				var eventsListDataGridOrderBy = '';
				var eventsListDataGridOrderByDirection = 'DESC';
				var eventsListDataGridSearchTerm = null;
				var eventsListDataGridRecordsPerPage = '15';

				var eventsListDataGridstatusFilter = null;

				function eventsListDataGridResetList(callBackFunction) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'resetSearchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: '' },
						function (data) {
							eventsListDataGridRefreshList(callBackFunction);
						}
					);
				}

				function eventsListDataGridRefreshList(callBackFunction) {
					var width = $("#eventsListDataGridTable").width();
					var height = $("#eventsListDataGridTable").height();

					if (width == 0)
						width = 400;
					if (height == 0)
						height = 200;

					var paddingLeft = Math.round(width / 2);

					eventsListDataGridWaitingDialog('');
					events2.admin.events.listEvents(0)( function (html) {
						eventsListDataGridRefreshListCallBack(html, callBackFunction);
					} );
				}

				function setHtmlWithoutScripts(elements, html) {
					return elements.each(function () {
						this.innerHTML = html;
					});
				}

				function eventsListDataGridRefreshListCallBack(result, callBackFunction) {
					if (result !== 1) {
						setHtmlWithoutScripts($('#eventsListDataGridTable'), result);
					}

					if (eventsListDataGridhasToolbar == 1) {
						eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
					}

					if (String(callBackFunction) != 'undefined') {
						var functionToCall = callBackFunction + '()';
						eval(functionToCall);
					}

					
					eventsListDataGridCloseWaitingDialog();
					$("#eventsListDataGridSearchInput").focus();				}

				function eventsListDataGridPageNumbers(pageNumber) {
					eventsListDataGridRangeStart = ( ( parseInt(pageNumber) - 1 ) * parseInt(15) );
					eventsListDataGridPageNumber = pageNumber;
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'rangeStart-PageNumber', id: 'eventsListDataGrid', value: eventsListDataGridRangeStart + '-' + eventsListDataGridPageNumber },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

				function eventsListDataGridSortList(orderBy, orderByDirection) {
					eventsListDataGridOrderBy = orderBy;
					eventsListDataGridOrderByDirection = orderByDirection;
					eventsListDataGridRangeStart = 0;
					eventsListDataGridPageNumber = 1;

					$.get('/administration/eventsadmin/events/sbHtml', { action: 'orderBy-orderByDirection-resetRangeStart-resetPageNumber', id: 'eventsListDataGrid', value: eventsListDataGridOrderBy + '-' + eventsListDataGridOrderByDirection },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

								eventsListDataGridRefreshList();
								$("#eventsListDataGridSearchInput").focus();
				SBHTML.add(new HtmlDataGrid("eventsListDataGrid"));

			
					
				
										
					
						Active
					
					
					
						Expired
					
					
					
						Not Public
					
				
									
										
			
		
		
							

							

							

						
					
					

				
			
			

			
			
				

					English Australia - Association Online Administration |
| English Australia - Association Online Administration

						
						

							

								

								Logged in as: Rodrigo Zillesg  
								 
								 
								 
								

							

						
					
					

					
					
						

							
							
								
								

									
									Dashboard
									
								
								
								

									
									People
									
								
								
								

									
									Members
									
								
								
								

									
									Events
									
								
								
								

									
									Marketing
									
								
								
								

									
									Commerce
									
								
								
								

									
									Website CMS
									
								
								
								

									
									Reports
									
								
								
								

									
									Tools |
| English Australia - Association Online Administration |
| Logged in as: Rodrigo Zillesg |
| Dashboard
									
								
								
								

									
									People
									
								
								
								

									
									Members
									
								
								
								

									
									Events
									
								
								
								

									
									Marketing
									
								
								
								

									
									Commerce
									
								
								
								

									
									Website CMS
									
								
								
								

									
									Reports
									
								
								
								

									
									Tools |
| Dashboard |
| People |
| Members |
| Events |
| Marketing |
| Commerce |
| Website CMS |
| Reports |
| Tools |
| Event Management
					

					
						
					
						Events
					
							
						
					
						Reports
					
							
						
					
						Events Import
					
							
						
					
						Email Log
					
							
						
					
						Import/Export
					
							
						
					
						Setup
					
							
						
					

					

					
						Abstracts
					

					
						
					

					

					
						Goto Webinar |
| events2.setSettings( {"ajaxPageUrl":"/administration/eventsadmin/events/ajax","editEventUrl":"/administration/eventsadmin/events/event"} ) 		
			
				
											
									
							Categories
						
							
				add_dom_javascript('/sb/modules/core/javascript/sb.js');
			
						
			var categoryToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function categoryToolbarUpdateToolbar( selections )
			{
				categoryToolbarSelections = selections;
								var btn = $("#categoryToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function categoryToolbarOnClick5()
					{
						var type = 5
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=addCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, categoryToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick1()
					{
						var type = 1
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=editCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick3()
					{
						var type = 3
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=deleteCategory&cid=0'}
						var onClickConfirmMessage = "Are you sure you want to delete the selected categories?";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function categoryToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = ""

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function categoryToolbarShowSaveMessage() {
				if ( document.getElementById("categoryToolbarSaveMessage") )
				{
					document.getElementById("categoryToolbarSaveMessage").style.display = 'block';
					window.setTimeout("categoryToolbarHideSaveMessage()", 1000);
				}
			}

			function categoryToolbarHideSaveMessage() {
				document.getElementById("categoryToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
					
			
				
				saved
			
						
				function loadTree() {
					$( "#tree" ).treeview( {
						persist: "location",
						collapsed: 1,
						unique:  false
					} );
				}
			
			
							
				Events
							
				English Australia Events
				
						
				Webinars
				
						
				Sector events 
				
						
				SIG Events
				
			
						
				Home Page Events
				
						
				Hidden From Public
				
						
				All
				
						
				Uncategorised
				
			
			
			
				loadTree();
						
		
								
													
								 
							
													
									
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
			var eventsListDataGridToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function eventsListDataGridToolbarUpdateToolbar( selections )
			{
				eventsListDataGridToolbarSelections = selections;
								var btn = $("#eventsListDataGridToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar4")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copy.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copyDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function eventsListDataGridToolbarOnClick5()
					{
						var type = 5
						var onClick = function () { events2.admin.events.add( -2 ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, eventsListDataGridToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick1()
					{
						var type = 1
						var onClick = ( function( value ) { events2.admin.events.edit( value ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick4()
					{
						var type = 4
						var onClick = ( function( value ) { events2.admin.events.copy( value, -2, "eventsListDataGrid" ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick3()
					{
						var type = 3
						var onClick = function ( value ) { events2.admin.events.deleteEvent( value, "eventsListDataGrid" ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function eventsListDataGridToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = "eventsListDataGrid"

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function eventsListDataGridToolbarShowSaveMessage() {
				if ( document.getElementById("eventsListDataGridToolbarSaveMessage") )
				{
					document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'block';
					window.setTimeout("eventsListDataGridToolbarHideSaveMessage()", 1000);
				}
			}

			function eventsListDataGridToolbarHideSaveMessage() {
				document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
										
					
			
				
				saved
			
							
					var eventsListDataGridTimer = 0;

					function eventsListDataGridStartTimer() {
						if (eventsListDataGridTimer > 0) {
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridIncrementTimer() {
						eventsListDataGridTimer += 5;

						if (eventsListDataGridTimer == 1000) {
							eventsListDataGridRunSearch();
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridRunSearch() {
						$.get('/administration/eventsadmin/events/sbHtml', { action: 'searchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: $('#eventsListDataGridSearchInput').val() },
								function(data)
								{
				    				eventsListDataGridSearchTerm = $('#eventsListDataGridSearchInput').val();
				    				eventsListDataGridRangeStart = 0;
				    				eventsListDataGridPageNumber = 1;
				    				eventsListDataGridRefreshList();
				    				
				  				}
				  				);					}

					function eventsListDataGridSearchOnEnter(e) {
						if (e.keyCode == 13) {
							eventsListDataGridRunSearch();
						}
					}
				
				Search			
						AllActiveExpiredUpcomingNot Public 			

				$(document).ready(function () {
					// create the loading window and set autoOpen to false
					$("#eventsListDataGridLoadingScreen").dialog({
						autoOpen: false,	// set this to false so we can manually open it
						dialogClass: "loadingScreenWindow",
						closeOnEscape: false,
						draggable: false,
						width: 460,
						minHeight: 50,
						modal: true,
						buttons: {},
						resizable: false,
						open: function () {
							// scrollbar fix for IE
							$('body').css('overflow', 'hidden');
						},
						close: function () {
							// reset overflow
							$('body').css('overflow', 'auto');
						}
					}); // end of dialog
				});

				function eventsListDataGridWaitingDialog(waiting) { // I choose to allow my loading screen dialog to be customizable, you don't have to
					var dialog = $("#eventsListDataGridLoadingScreen");

					dialog.html(waiting.message && '' != waiting.message ? waiting.message : 'Please wait...');
					dialog.dialog({ autoOpen: false })
					dialog.dialog('option', 'title', waiting.title && '' != waiting.title ? waiting.title : 'Loading');
					dialog.dialog('open');
				}

				function eventsListDataGridCloseWaitingDialog() {
					var dialog = $("#eventsListDataGridLoadingScreen");
					dialog.dialog({autoOpen: false})
					dialog.dialog('close');
					dialog.remove()
				}
			
						
			
			
									
						From					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeFrom";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeFromClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
					
						 
					
										
						To					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeTo";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeToClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
								
			
			

			

				SBHTML.add( new HtmlDateRange( 'eventsListDataGridDateRange', {} ) );

			
					
			
				
				
											
							 						
												
															Event Title													
												
															Start Date													
												
															Periods													
												
															Status													
												
															Places													
												
															Registrations to date													
										
				
				
							
									
								
							
					
				

				
						
							
									
						Managing classroom activities					
									
						11th Jun 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Teaching with What’s Real: Australian Content, and Practical ELICOS Materials					
									
						12th May 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Exploiting learning resources					
									
						8th Apr 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Making intensive reading work: Practical strategies for the English language classroom					
									
						11th Mar 26					
									
						1					
									
						Active					
									
											
									
						12					
							
						
									
								
							
					
				

				
						
							
									
						Teaching mixed-level classes					
									
						11th Feb 26					
									
						1					
									
						Active					
									
											
									
						15					
							
						
									
								
							
					
				

				
						
							
									
						A Taste of the English Australia Conference - Melbourne					
									
						29th Jan 26					
									
						1					
									
						Active					
									
											
									
						6					
							
						
									
								
							
					
				

				
						
							
									
						Online Member Meeting re Education Legislation Amendment Bill					
									
						5th Dec 25					
									
						1					
									
						Expired					
									
											
									
						123					
							
						
									
								
							
					
				

				
						
							
									
						Reassessing Assessment in EAP: A student perspective					
									
						2nd Dec 25					
									
						1					
									
						Expired					
									
											
									
						74					
							
						
									
								
							
					
				

				
						
							
									
						Vic Advisors Meeting					
									
						27th Nov 25					
									
						1					
									
						Expired					
									
											
									
						20					
							
						
									
								
							
					
				

				
						
							
									
						Using Custom GPTs in English language teaching and learning					
									
						27th Nov 25					
									
						1					
									
						Not Public					
									
											
									
						42					
							
						
									
								
							
					
				

				
						
							
									
						2025 Ed-Tech Symposium					
									
						12th Nov 25					
									
						1					
									
						Expired					
									
											
									
						149					
							
						
									
								
							
					
				

				
						
							
									
						AI Bots vs Boffins: How can we best assist our students?					
									
						5th Nov 25					
									
						1					
									
						Expired					
									
											
									
						103					
							
						
									
								
							
					
				

				
						
							
									
						Impacting and Inspiring Neurodiverse Learners					
									
						29th Oct 25					
									
						1					
									
						Expired					
									
											
									
						81					
							
						
									
								
							
					
				

				
						
							
									
						Building Custom GPTs					
									
						23rd Oct 25					
									
						1					
									
						Expired					
									
						130					
									
						130					
							
						
									
								
							
					
				

				
						
							
									
						Member Meeting: Changes to the English Australia Constitution					
									
						22nd Oct 25					
									
						1					
									
						Expired					
									
											
									
						18					
							
							
			
		
	[ 411 records ]1
2
3
4
5
6
7
8
9
10
 | 11 - 20
 | next >
 | last >>
[ 28 pages ]		15
			
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
				var eventsListDataGridRangeStart = '0';
				var eventsListDataGridAlphabet = '0';
				var eventsListDataGridPageNumber = '1';
				var eventsListDataGridOrderBy = '';
				var eventsListDataGridOrderByDirection = 'DESC';
				var eventsListDataGridSearchTerm = null;
				var eventsListDataGridRecordsPerPage = '15';

				var eventsListDataGridstatusFilter = null;

				function eventsListDataGridResetList(callBackFunction) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'resetSearchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: '' },
						function (data) {
							eventsListDataGridRefreshList(callBackFunction);
						}
					);
				}

				function eventsListDataGridRefreshList(callBackFunction) {
					var width = $("#eventsListDataGridTable").width();
					var height = $("#eventsListDataGridTable").height();

					if (width == 0)
						width = 400;
					if (height == 0)
						height = 200;

					var paddingLeft = Math.round(width / 2);

					eventsListDataGridWaitingDialog('');
					events2.admin.events.listEvents(0)( function (html) {
						eventsListDataGridRefreshListCallBack(html, callBackFunction);
					} );
				}

				function setHtmlWithoutScripts(elements, html) {
					return elements.each(function () {
						this.innerHTML = html;
					});
				}

				function eventsListDataGridRefreshListCallBack(result, callBackFunction) {
					if (result !== 1) {
						setHtmlWithoutScripts($('#eventsListDataGridTable'), result);
					}

					if (eventsListDataGridhasToolbar == 1) {
						eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
					}

					if (String(callBackFunction) != 'undefined') {
						var functionToCall = callBackFunction + '()';
						eval(functionToCall);
					}

					
					eventsListDataGridCloseWaitingDialog();
					$("#eventsListDataGridSearchInput").focus();				}

				function eventsListDataGridPageNumbers(pageNumber) {
					eventsListDataGridRangeStart = ( ( parseInt(pageNumber) - 1 ) * parseInt(15) );
					eventsListDataGridPageNumber = pageNumber;
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'rangeStart-PageNumber', id: 'eventsListDataGrid', value: eventsListDataGridRangeStart + '-' + eventsListDataGridPageNumber },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

				function eventsListDataGridSortList(orderBy, orderByDirection) {
					eventsListDataGridOrderBy = orderBy;
					eventsListDataGridOrderByDirection = orderByDirection;
					eventsListDataGridRangeStart = 0;
					eventsListDataGridPageNumber = 1;

					$.get('/administration/eventsadmin/events/sbHtml', { action: 'orderBy-orderByDirection-resetRangeStart-resetPageNumber', id: 'eventsListDataGrid', value: eventsListDataGridOrderBy + '-' + eventsListDataGridOrderByDirection },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

								eventsListDataGridRefreshList();
								$("#eventsListDataGridSearchInput").focus();
				SBHTML.add(new HtmlDataGrid("eventsListDataGrid"));

			
					
				
										
					
						Active
					
					
					
						Expired
					
					
					
						Not Public |
| events2.setSettings( {"ajaxPageUrl":"/administration/eventsadmin/events/ajax","editEventUrl":"/administration/eventsadmin/events/event"} ) 		
			
				
											
									
							Categories
						
							
				add_dom_javascript('/sb/modules/core/javascript/sb.js');
			
						
			var categoryToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function categoryToolbarUpdateToolbar( selections )
			{
				categoryToolbarSelections = selections;
								var btn = $("#categoryToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function categoryToolbarOnClick5()
					{
						var type = 5
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=addCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, categoryToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick1()
					{
						var type = 1
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=editCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick3()
					{
						var type = 3
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=deleteCategory&cid=0'}
						var onClickConfirmMessage = "Are you sure you want to delete the selected categories?";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function categoryToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = ""

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function categoryToolbarShowSaveMessage() {
				if ( document.getElementById("categoryToolbarSaveMessage") )
				{
					document.getElementById("categoryToolbarSaveMessage").style.display = 'block';
					window.setTimeout("categoryToolbarHideSaveMessage()", 1000);
				}
			}

			function categoryToolbarHideSaveMessage() {
				document.getElementById("categoryToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
					
			
				
				saved
			
						
				function loadTree() {
					$( "#tree" ).treeview( {
						persist: "location",
						collapsed: 1,
						unique:  false
					} );
				}
			
			
							
				Events
							
				English Australia Events
				
						
				Webinars
				
						
				Sector events 
				
						
				SIG Events
				
			
						
				Home Page Events
				
						
				Hidden From Public
				
						
				All
				
						
				Uncategorised
				
			
			
			
				loadTree();
						
		
								
													
								 
							
													
									
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
			var eventsListDataGridToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function eventsListDataGridToolbarUpdateToolbar( selections )
			{
				eventsListDataGridToolbarSelections = selections;
								var btn = $("#eventsListDataGridToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar4")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copy.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copyDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function eventsListDataGridToolbarOnClick5()
					{
						var type = 5
						var onClick = function () { events2.admin.events.add( -2 ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, eventsListDataGridToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick1()
					{
						var type = 1
						var onClick = ( function( value ) { events2.admin.events.edit( value ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick4()
					{
						var type = 4
						var onClick = ( function( value ) { events2.admin.events.copy( value, -2, "eventsListDataGrid" ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick3()
					{
						var type = 3
						var onClick = function ( value ) { events2.admin.events.deleteEvent( value, "eventsListDataGrid" ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function eventsListDataGridToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = "eventsListDataGrid"

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function eventsListDataGridToolbarShowSaveMessage() {
				if ( document.getElementById("eventsListDataGridToolbarSaveMessage") )
				{
					document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'block';
					window.setTimeout("eventsListDataGridToolbarHideSaveMessage()", 1000);
				}
			}

			function eventsListDataGridToolbarHideSaveMessage() {
				document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
										
					
			
				
				saved
			
							
					var eventsListDataGridTimer = 0;

					function eventsListDataGridStartTimer() {
						if (eventsListDataGridTimer > 0) {
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridIncrementTimer() {
						eventsListDataGridTimer += 5;

						if (eventsListDataGridTimer == 1000) {
							eventsListDataGridRunSearch();
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridRunSearch() {
						$.get('/administration/eventsadmin/events/sbHtml', { action: 'searchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: $('#eventsListDataGridSearchInput').val() },
								function(data)
								{
				    				eventsListDataGridSearchTerm = $('#eventsListDataGridSearchInput').val();
				    				eventsListDataGridRangeStart = 0;
				    				eventsListDataGridPageNumber = 1;
				    				eventsListDataGridRefreshList();
				    				
				  				}
				  				);					}

					function eventsListDataGridSearchOnEnter(e) {
						if (e.keyCode == 13) {
							eventsListDataGridRunSearch();
						}
					}
				
				Search			
						AllActiveExpiredUpcomingNot Public 			

				$(document).ready(function () {
					// create the loading window and set autoOpen to false
					$("#eventsListDataGridLoadingScreen").dialog({
						autoOpen: false,	// set this to false so we can manually open it
						dialogClass: "loadingScreenWindow",
						closeOnEscape: false,
						draggable: false,
						width: 460,
						minHeight: 50,
						modal: true,
						buttons: {},
						resizable: false,
						open: function () {
							// scrollbar fix for IE
							$('body').css('overflow', 'hidden');
						},
						close: function () {
							// reset overflow
							$('body').css('overflow', 'auto');
						}
					}); // end of dialog
				});

				function eventsListDataGridWaitingDialog(waiting) { // I choose to allow my loading screen dialog to be customizable, you don't have to
					var dialog = $("#eventsListDataGridLoadingScreen");

					dialog.html(waiting.message && '' != waiting.message ? waiting.message : 'Please wait...');
					dialog.dialog({ autoOpen: false })
					dialog.dialog('option', 'title', waiting.title && '' != waiting.title ? waiting.title : 'Loading');
					dialog.dialog('open');
				}

				function eventsListDataGridCloseWaitingDialog() {
					var dialog = $("#eventsListDataGridLoadingScreen");
					dialog.dialog({autoOpen: false})
					dialog.dialog('close');
					dialog.remove()
				}
			
						
			
			
									
						From					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeFrom";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeFromClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
					
						 
					
										
						To					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeTo";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeToClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
								
			
			

			

				SBHTML.add( new HtmlDateRange( 'eventsListDataGridDateRange', {} ) );

			
					
			
				
				
											
							 						
												
															Event Title													
												
															Start Date													
												
															Periods													
												
															Status													
												
															Places													
												
															Registrations to date													
										
				
				
							
									
								
							
					
				

				
						
							
									
						Managing classroom activities					
									
						11th Jun 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Teaching with What’s Real: Australian Content, and Practical ELICOS Materials					
									
						12th May 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Exploiting learning resources					
									
						8th Apr 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Making intensive reading work: Practical strategies for the English language classroom					
									
						11th Mar 26					
									
						1					
									
						Active					
									
											
									
						12					
							
						
									
								
							
					
				

				
						
							
									
						Teaching mixed-level classes					
									
						11th Feb 26					
									
						1					
									
						Active					
									
											
									
						15					
							
						
									
								
							
					
				

				
						
							
									
						A Taste of the English Australia Conference - Melbourne					
									
						29th Jan 26					
									
						1					
									
						Active					
									
											
									
						6					
							
						
									
								
							
					
				

				
						
							
									
						Online Member Meeting re Education Legislation Amendment Bill					
									
						5th Dec 25					
									
						1					
									
						Expired					
									
											
									
						123					
							
						
									
								
							
					
				

				
						
							
									
						Reassessing Assessment in EAP: A student perspective					
									
						2nd Dec 25					
									
						1					
									
						Expired					
									
											
									
						74					
							
						
									
								
							
					
				

				
						
							
									
						Vic Advisors Meeting					
									
						27th Nov 25					
									
						1					
									
						Expired					
									
											
									
						20					
							
						
									
								
							
					
				

				
						
							
									
						Using Custom GPTs in English language teaching and learning					
									
						27th Nov 25					
									
						1					
									
						Not Public					
									
											
									
						42					
							
						
									
								
							
					
				

				
						
							
									
						2025 Ed-Tech Symposium					
									
						12th Nov 25					
									
						1					
									
						Expired					
									
											
									
						149					
							
						
									
								
							
					
				

				
						
							
									
						AI Bots vs Boffins: How can we best assist our students?					
									
						5th Nov 25					
									
						1					
									
						Expired					
									
											
									
						103					
							
						
									
								
							
					
				

				
						
							
									
						Impacting and Inspiring Neurodiverse Learners					
									
						29th Oct 25					
									
						1					
									
						Expired					
									
											
									
						81					
							
						
									
								
							
					
				

				
						
							
									
						Building Custom GPTs					
									
						23rd Oct 25					
									
						1					
									
						Expired					
									
						130					
									
						130					
							
						
									
								
							
					
				

				
						
							
									
						Member Meeting: Changes to the English Australia Constitution					
									
						22nd Oct 25					
									
						1					
									
						Expired					
									
											
									
						18					
							
							
			
		
	[ 411 records ]1
2
3
4
5
6
7
8
9
10
 | 11 - 20
 | next >
 | last >>
[ 28 pages ]		15
			
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
				var eventsListDataGridRangeStart = '0';
				var eventsListDataGridAlphabet = '0';
				var eventsListDataGridPageNumber = '1';
				var eventsListDataGridOrderBy = '';
				var eventsListDataGridOrderByDirection = 'DESC';
				var eventsListDataGridSearchTerm = null;
				var eventsListDataGridRecordsPerPage = '15';

				var eventsListDataGridstatusFilter = null;

				function eventsListDataGridResetList(callBackFunction) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'resetSearchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: '' },
						function (data) {
							eventsListDataGridRefreshList(callBackFunction);
						}
					);
				}

				function eventsListDataGridRefreshList(callBackFunction) {
					var width = $("#eventsListDataGridTable").width();
					var height = $("#eventsListDataGridTable").height();

					if (width == 0)
						width = 400;
					if (height == 0)
						height = 200;

					var paddingLeft = Math.round(width / 2);

					eventsListDataGridWaitingDialog('');
					events2.admin.events.listEvents(0)( function (html) {
						eventsListDataGridRefreshListCallBack(html, callBackFunction);
					} );
				}

				function setHtmlWithoutScripts(elements, html) {
					return elements.each(function () {
						this.innerHTML = html;
					});
				}

				function eventsListDataGridRefreshListCallBack(result, callBackFunction) {
					if (result !== 1) {
						setHtmlWithoutScripts($('#eventsListDataGridTable'), result);
					}

					if (eventsListDataGridhasToolbar == 1) {
						eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
					}

					if (String(callBackFunction) != 'undefined') {
						var functionToCall = callBackFunction + '()';
						eval(functionToCall);
					}

					
					eventsListDataGridCloseWaitingDialog();
					$("#eventsListDataGridSearchInput").focus();				}

				function eventsListDataGridPageNumbers(pageNumber) {
					eventsListDataGridRangeStart = ( ( parseInt(pageNumber) - 1 ) * parseInt(15) );
					eventsListDataGridPageNumber = pageNumber;
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'rangeStart-PageNumber', id: 'eventsListDataGrid', value: eventsListDataGridRangeStart + '-' + eventsListDataGridPageNumber },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

				function eventsListDataGridSortList(orderBy, orderByDirection) {
					eventsListDataGridOrderBy = orderBy;
					eventsListDataGridOrderByDirection = orderByDirection;
					eventsListDataGridRangeStart = 0;
					eventsListDataGridPageNumber = 1;

					$.get('/administration/eventsadmin/events/sbHtml', { action: 'orderBy-orderByDirection-resetRangeStart-resetPageNumber', id: 'eventsListDataGrid', value: eventsListDataGridOrderBy + '-' + eventsListDataGridOrderByDirection },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

								eventsListDataGridRefreshList();
								$("#eventsListDataGridSearchInput").focus();
				SBHTML.add(new HtmlDataGrid("eventsListDataGrid"));

			
					
				
										
					
						Active
					
					
					
						Expired
					
					
					
						Not Public |
| Categories
						
							
				add_dom_javascript('/sb/modules/core/javascript/sb.js');
			
						
			var categoryToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function categoryToolbarUpdateToolbar( selections )
			{
				categoryToolbarSelections = selections;
								var btn = $("#categoryToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#categoryToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function categoryToolbarOnClick5()
					{
						var type = 5
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=addCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, categoryToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick1()
					{
						var type = 1
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=editCategory&cid=0'}
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function categoryToolbarOnClick3()
					{
						var type = 3
						var onClick = function(){document.location = '/administration/eventsadmin/events/categoryManager?command=deleteCategory&cid=0'}
						var onClickConfirmMessage = "Are you sure you want to delete the selected categories?";
						var clearDataGridSelection = true

						if ( !false || categoryToolbarSelections.length >= 1 )
							categoryToolbarProcessOnClick( onClick, sb.implode(',', categoryToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function categoryToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = ""

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function categoryToolbarShowSaveMessage() {
				if ( document.getElementById("categoryToolbarSaveMessage") )
				{
					document.getElementById("categoryToolbarSaveMessage").style.display = 'block';
					window.setTimeout("categoryToolbarHideSaveMessage()", 1000);
				}
			}

			function categoryToolbarHideSaveMessage() {
				document.getElementById("categoryToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
					
			
				
				saved
			
						
				function loadTree() {
					$( "#tree" ).treeview( {
						persist: "location",
						collapsed: 1,
						unique:  false
					} );
				}
			
			
							
				Events
							
				English Australia Events
				
						
				Webinars
				
						
				Sector events 
				
						
				SIG Events
				
			
						
				Home Page Events
				
						
				Hidden From Public
				
						
				All
				
						
				Uncategorised
				
			
			
			
				loadTree(); |
|  |
| var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
			var eventsListDataGridToolbarSelections = [];
			
			// selectionsArray should be an array of ids
			function eventsListDataGridToolbarUpdateToolbar( selections )
			{
				eventsListDataGridToolbarSelections = selections;
								var btn = $("#eventsListDataGridToolbar5")
				if ( true )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/addIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar1")
				if ( selections.length == 1 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/editIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar4")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copy.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/copyDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}
				var btn = $("#eventsListDataGridToolbar3")
				if ( selections.length > 0 )
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIcon.png)');
					btn.attr('class', 'sbToolbarButton');
					btn.sbPropCompat('disabled', '');
				}
				else
				{
					btn.attr('style', 'background-image: url(/sb/styles/glossybar/images/deleteIconDisabled.png)');
					btn.attr('class', 'sbToolbarButtonDisabled');
					btn.sbPropCompat('disabled', 'disabled');
				}

			}

								function eventsListDataGridToolbarOnClick5()
					{
						var type = 5
						var onClick = function () { events2.admin.events.add( -2 ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, eventsListDataGridToolbarSelections[0], onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick1()
					{
						var type = 1
						var onClick = ( function( value ) { events2.admin.events.edit( value ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick4()
					{
						var type = 4
						var onClick = ( function( value ) { events2.admin.events.copy( value, -2, "eventsListDataGrid" ) } )
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }
					function eventsListDataGridToolbarOnClick3()
					{
						var type = 3
						var onClick = function ( value ) { events2.admin.events.deleteEvent( value, "eventsListDataGrid" ) }
						var onClickConfirmMessage = "";
						var clearDataGridSelection = true

						if ( !false || eventsListDataGridToolbarSelections.length >= 1 )
							eventsListDataGridToolbarProcessOnClick( onClick, sb.implode(',', eventsListDataGridToolbarSelections), onClickConfirmMessage, clearDataGridSelection, type )
					 }


			function eventsListDataGridToolbarProcessOnClick( onClick, value, onClickConfirmMessage, clearDataGridSelection, type )
			{
				var datagridId = "eventsListDataGrid"

				if ( onClickConfirmMessage.length > 0 && !confirm( onClickConfirmMessage ) )
					return;

				var callFunctionViaEval = function( toCallString, arg ) {
					toCallString( arg );
				};

				if ( datagridId.length > 0 && clearDataGridSelection )
				{
					//rangeStart-PageNumber
					var actionName = 'removeSelectedCheckboxes';
					if ( type == 3 )
					{
						actionName = 'removeSelectedCheckboxes-resetRangeStart-resetPageNumber';
					}
					$.get("/administration/eventsadmin/events/sbHtml", { action: actionName, id: datagridId, value: value },
						function(data)
						{
							callFunctionViaEval( onClick, value );
						}
					);
				}
				else
				{
					callFunctionViaEval( onClick, value );
				}
			}

			function eventsListDataGridToolbarShowSaveMessage() {
				if ( document.getElementById("eventsListDataGridToolbarSaveMessage") )
				{
					document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'block';
					window.setTimeout("eventsListDataGridToolbarHideSaveMessage()", 1000);
				}
			}

			function eventsListDataGridToolbarHideSaveMessage() {
				document.getElementById("eventsListDataGridToolbarSaveMessage").style.display = 'none';
			}
			
			
								
										
										
										
					
			
				
				saved
			
							
					var eventsListDataGridTimer = 0;

					function eventsListDataGridStartTimer() {
						if (eventsListDataGridTimer > 0) {
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridIncrementTimer() {
						eventsListDataGridTimer += 5;

						if (eventsListDataGridTimer == 1000) {
							eventsListDataGridRunSearch();
							eventsListDataGridTimer = 0;
						}
						else {
							setTimeout("eventsListDataGridIncrementTimer()", 5);
						}
					}

					function eventsListDataGridRunSearch() {
						$.get('/administration/eventsadmin/events/sbHtml', { action: 'searchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: $('#eventsListDataGridSearchInput').val() },
								function(data)
								{
				    				eventsListDataGridSearchTerm = $('#eventsListDataGridSearchInput').val();
				    				eventsListDataGridRangeStart = 0;
				    				eventsListDataGridPageNumber = 1;
				    				eventsListDataGridRefreshList();
				    				
				  				}
				  				);					}

					function eventsListDataGridSearchOnEnter(e) {
						if (e.keyCode == 13) {
							eventsListDataGridRunSearch();
						}
					}
				
				Search			
						AllActiveExpiredUpcomingNot Public 			

				$(document).ready(function () {
					// create the loading window and set autoOpen to false
					$("#eventsListDataGridLoadingScreen").dialog({
						autoOpen: false,	// set this to false so we can manually open it
						dialogClass: "loadingScreenWindow",
						closeOnEscape: false,
						draggable: false,
						width: 460,
						minHeight: 50,
						modal: true,
						buttons: {},
						resizable: false,
						open: function () {
							// scrollbar fix for IE
							$('body').css('overflow', 'hidden');
						},
						close: function () {
							// reset overflow
							$('body').css('overflow', 'auto');
						}
					}); // end of dialog
				});

				function eventsListDataGridWaitingDialog(waiting) { // I choose to allow my loading screen dialog to be customizable, you don't have to
					var dialog = $("#eventsListDataGridLoadingScreen");

					dialog.html(waiting.message && '' != waiting.message ? waiting.message : 'Please wait...');
					dialog.dialog({ autoOpen: false })
					dialog.dialog('option', 'title', waiting.title && '' != waiting.title ? waiting.title : 'Loading');
					dialog.dialog('open');
				}

				function eventsListDataGridCloseWaitingDialog() {
					var dialog = $("#eventsListDataGridLoadingScreen");
					dialog.dialog({autoOpen: false})
					dialog.dialog('close');
					dialog.remove()
				}
			
						
			
			
									
						From					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeFrom";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeFromClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
					
						 
					
										
						To					
					
											

						ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeTo";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeToClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear
						
									
								
			
			

			

				SBHTML.add( new HtmlDateRange( 'eventsListDataGridDateRange', {} ) );

			
					
			
				
				
											
							 						
												
															Event Title													
												
															Start Date													
												
															Periods													
												
															Status													
												
															Places													
												
															Registrations to date													
										
				
				
							
									
								
							
					
				

				
						
							
									
						Managing classroom activities					
									
						11th Jun 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Teaching with What’s Real: Australian Content, and Practical ELICOS Materials					
									
						12th May 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Exploiting learning resources					
									
						8th Apr 26					
									
						1					
									
						Active					
									
											
									
						0					
							
						
									
								
							
					
				

				
						
							
									
						Making intensive reading work: Practical strategies for the English language classroom					
									
						11th Mar 26					
									
						1					
									
						Active					
									
											
									
						12					
							
						
									
								
							
					
				

				
						
							
									
						Teaching mixed-level classes					
									
						11th Feb 26					
									
						1					
									
						Active					
									
											
									
						15					
							
						
									
								
							
					
				

				
						
							
									
						A Taste of the English Australia Conference - Melbourne					
									
						29th Jan 26					
									
						1					
									
						Active					
									
											
									
						6					
							
						
									
								
							
					
				

				
						
							
									
						Online Member Meeting re Education Legislation Amendment Bill					
									
						5th Dec 25					
									
						1					
									
						Expired					
									
											
									
						123					
							
						
									
								
							
					
				

				
						
							
									
						Reassessing Assessment in EAP: A student perspective					
									
						2nd Dec 25					
									
						1					
									
						Expired					
									
											
									
						74					
							
						
									
								
							
					
				

				
						
							
									
						Vic Advisors Meeting					
									
						27th Nov 25					
									
						1					
									
						Expired					
									
											
									
						20					
							
						
									
								
							
					
				

				
						
							
									
						Using Custom GPTs in English language teaching and learning					
									
						27th Nov 25					
									
						1					
									
						Not Public					
									
											
									
						42					
							
						
									
								
							
					
				

				
						
							
									
						2025 Ed-Tech Symposium					
									
						12th Nov 25					
									
						1					
									
						Expired					
									
											
									
						149					
							
						
									
								
							
					
				

				
						
							
									
						AI Bots vs Boffins: How can we best assist our students?					
									
						5th Nov 25					
									
						1					
									
						Expired					
									
											
									
						103					
							
						
									
								
							
					
				

				
						
							
									
						Impacting and Inspiring Neurodiverse Learners					
									
						29th Oct 25					
									
						1					
									
						Expired					
									
											
									
						81					
							
						
									
								
							
					
				

				
						
							
									
						Building Custom GPTs					
									
						23rd Oct 25					
									
						1					
									
						Expired					
									
						130					
									
						130					
							
						
									
								
							
					
				

				
						
							
									
						Member Meeting: Changes to the English Australia Constitution					
									
						22nd Oct 25					
									
						1					
									
						Expired					
									
											
									
						18					
							
							
			
		
	[ 411 records ]1
2
3
4
5
6
7
8
9
10
 | 11 - 20
 | next >
 | last >>
[ 28 pages ]		15
			
			var eventsListDataGridLoading = '<img src="https://www.englishaustralia.com.au/sb/styles/glossybar/images/loadergif.gif" width="16" height="16" alt="" />';
			var eventsListDataGridhasToolbar = 1;
			var eventsListDataGridhasAlphabet = 0;

			function eventsListDataGridCheckedArray() {
				var checkedArray = [];
				var c = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i + ":checked").val() != undefined) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						c++;
					}
				}

				return checkedArray;
			}

			function eventsListDataGridsetActionCheckbox(on, value, row, originalClass, update) {
				var className = '';
				var action = '';

				if (on) {
					className = 'rowOn';
					action = 'addSelectedCheckbox';
				}
				else {
					className = originalClass;
					action = 'removeSelectedCheckbox';
				}

				$("#eventsListDataGridRow" + row).attr('class', className);
				if (update == 1) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: action, id: 'eventsListDataGrid', value: value },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						}
					);
				}
			}

			function eventsListDataGridcheckAll() {
				var checkedArray = [];
				var c = 0;
				var className = '';
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == false) {
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', true);
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, $("#eventsListDataGridActionCheckboxes" + i).attr('class'), 0);

						c++;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'addSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}

			function eventsListDataGridunCheckAll() {
				var checkedArray = [];
				var c = 0;
				var s = 0;
				for (i = 0; i < $("#eventsListDataGridActionCheckboxesTotal").html(); i++) {
					if (s == 0) {
						className = 'row0';
					}
					else {
						className = 'row1';
					}

					if ($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked') == true) {
						checkedArray[c] = $("#eventsListDataGridActionCheckboxes" + i + ":checked").val();
						$("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked', false);

						eventsListDataGridsetActionCheckbox($("#eventsListDataGridActionCheckboxes" + i).sbPropCompat('checked'), checkedArray[c], c, className, 0);

						c++;
					}
					s++;
					if (s == 2) {
						s = 0;
					}
				}

				if (checkedArray.length > 0) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'removeSelectedCheckboxes', id: 'eventsListDataGrid', value: sb.implode(',', checkedArray) },
						function (data) {
							if (eventsListDataGridhasToolbar == 1) {
								eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
							}
						});
				}
			}
		
				
				var eventsListDataGridRangeStart = '0';
				var eventsListDataGridAlphabet = '0';
				var eventsListDataGridPageNumber = '1';
				var eventsListDataGridOrderBy = '';
				var eventsListDataGridOrderByDirection = 'DESC';
				var eventsListDataGridSearchTerm = null;
				var eventsListDataGridRecordsPerPage = '15';

				var eventsListDataGridstatusFilter = null;

				function eventsListDataGridResetList(callBackFunction) {
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'resetSearchTerm-resetRangeStart-resetPageNumber-resetAlphabet', id: 'eventsListDataGrid', value: '' },
						function (data) {
							eventsListDataGridRefreshList(callBackFunction);
						}
					);
				}

				function eventsListDataGridRefreshList(callBackFunction) {
					var width = $("#eventsListDataGridTable").width();
					var height = $("#eventsListDataGridTable").height();

					if (width == 0)
						width = 400;
					if (height == 0)
						height = 200;

					var paddingLeft = Math.round(width / 2);

					eventsListDataGridWaitingDialog('');
					events2.admin.events.listEvents(0)( function (html) {
						eventsListDataGridRefreshListCallBack(html, callBackFunction);
					} );
				}

				function setHtmlWithoutScripts(elements, html) {
					return elements.each(function () {
						this.innerHTML = html;
					});
				}

				function eventsListDataGridRefreshListCallBack(result, callBackFunction) {
					if (result !== 1) {
						setHtmlWithoutScripts($('#eventsListDataGridTable'), result);
					}

					if (eventsListDataGridhasToolbar == 1) {
						eventsListDataGridToolbarUpdateToolbar(eventsListDataGridCheckedArray());
					}

					if (String(callBackFunction) != 'undefined') {
						var functionToCall = callBackFunction + '()';
						eval(functionToCall);
					}

					
					eventsListDataGridCloseWaitingDialog();
					$("#eventsListDataGridSearchInput").focus();				}

				function eventsListDataGridPageNumbers(pageNumber) {
					eventsListDataGridRangeStart = ( ( parseInt(pageNumber) - 1 ) * parseInt(15) );
					eventsListDataGridPageNumber = pageNumber;
					$.get('/administration/eventsadmin/events/sbHtml', { action: 'rangeStart-PageNumber', id: 'eventsListDataGrid', value: eventsListDataGridRangeStart + '-' + eventsListDataGridPageNumber },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

				function eventsListDataGridSortList(orderBy, orderByDirection) {
					eventsListDataGridOrderBy = orderBy;
					eventsListDataGridOrderByDirection = orderByDirection;
					eventsListDataGridRangeStart = 0;
					eventsListDataGridPageNumber = 1;

					$.get('/administration/eventsadmin/events/sbHtml', { action: 'orderBy-orderByDirection-resetRangeStart-resetPageNumber', id: 'eventsListDataGrid', value: eventsListDataGridOrderBy + '-' + eventsListDataGridOrderByDirection },
						function (data) {
							eventsListDataGridRefreshList();
						}
					);
				}

								eventsListDataGridRefreshList();
								$("#eventsListDataGridSearchInput").focus();
				SBHTML.add(new HtmlDataGrid("eventsListDataGrid"));

			
					
				
										
					
						Active
					
					
					
						Expired
					
					
					
						Not Public |
| Search |
|  |
| AllActiveExpiredUpcomingNot Public |
|  |
| From |
| ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeFrom";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeFromClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear |
|  |
| To |
| ivt.jQuery((function() {
	
							var ourId = "eventsListDataGridDateRangeTo";
	
							var update = function (id) {

								var alternateId = id + 'Alternate';
								var clearButtonId = id + 'ClearButton';

								var datePicker = ivt.jQuery( "#" + id );
								datePicker.datepicker({
									altField: '#' + alternateId,
									altFormat: "yy-mm-dd", 
									dateFormat: "dd/mm/yy",
									maxDate: null,
									minDate: null,
	
									showOn: "both",
	
									buttonImage: "\/sb\/styles\/glossybar\/images\/calendar.png",
									buttonImageOnly: true,
									buttonText: 'Select Date',
	
									changeYear: true,
									changeMonth: true,
									yearRange: '1900:2050'
								});
								datePicker.keypress( function( event ) {
									if (ivt.jQuery.inArray(event.key,["0","1","2","3","4","5","6","7","8","9","/","Backspace","ArrowLeft","ArrowRight"]) === -1) {
										event.preventDefault()
									}
								} ) ;

								
								ivt.jQuery('#' + clearButtonId).unbind('click').click(function () {
									ivt.jQuery('#' + alternateId + ', #' + id).val('').trigger('change');
								});
	
							};
	
								
								update(ourId);
	
									
						}));
	
					
									
				
					
					
							
		function eventsListDataGridDateRangeToClearButtonOnClick( onClick, onClickConfirmMessage )
		{
			if ( onClick.length > 0 )
			{
				var proceed = true;
				if ( onClickConfirmMessage.length > 0 )
				{
					proceed = confirm( onClickConfirmMessage );
				}

				if ( proceed )
				{
					eval( onClick );
				}
			}
		}
		

		Clear |
|  |
| Event Title |
| Start Date |
| Periods |
| Status |
| Places |
| Registrations to date |
|  |
| Managing classroom activities |
| 11th Jun 26 |
| 1 |
| Active |
|  |
| 0 |
|  |
| Teaching with What’s Real: Australian Content, and Practical ELICOS Materials |
| 12th May 26 |
| 1 |
| Active |
|  |
| 0 |
|  |
| Exploiting learning resources |
| 8th Apr 26 |
| 1 |
| Active |
|  |
| 0 |
|  |
| Making intensive reading work: Practical strategies for the English language classroom |
| 11th Mar 26 |
| 1 |
| Active |
|  |
| 12 |
|  |
| Teaching mixed-level classes |
| 11th Feb 26 |
| 1 |
| Active |
|  |
| 15 |
|  |
| A Taste of the English Australia Conference - Melbourne |
| 29th Jan 26 |
| 1 |
| Active |
|  |
| 6 |
|  |
| Online Member Meeting re Education Legislation Amendment Bill |
| 5th Dec 25 |
| 1 |
| Expired |
|  |
| 123 |
|  |
| Reassessing Assessment in EAP: A student perspective |
| 2nd Dec 25 |
| 1 |
| Expired |
|  |
| 74 |
|  |
| Vic Advisors Meeting |
| 27th Nov 25 |
| 1 |
| Expired |
|  |
| 20 |
|  |
| Using Custom GPTs in English language teaching and learning |
| 27th Nov 25 |
| 1 |
| Not Public |
|  |
| 42 |
|  |
| 2025 Ed-Tech Symposium |
| 12th Nov 25 |
| 1 |
| Expired |
|  |
| 149 |
|  |
| AI Bots vs Boffins: How can we best assist our students? |
| 5th Nov 25 |
| 1 |
| Expired |
|  |
| 103 |
|  |
| Impacting and Inspiring Neurodiverse Learners |
| 29th Oct 25 |
| 1 |
| Expired |
|  |
| 81 |
|  |
| Building Custom GPTs |
| 23rd Oct 25 |
| 1 |
| Expired |
| 130 |
| 130 |
|  |
| Member Meeting: Changes to the English Australia Constitution |
| 22nd Oct 25 |
| 1 |
| Expired |
|  |
| 18 |
| [ 411 records ] |
| 1 |
| 2 |
| 3 |
| 4 |
| 5 |
| 6 |
| 7 |
| 8 |
| 9 |
| 10 |
| | 11 - 20 |
| | next > |
| | last >> |
| [ 28 pages ] |
|  |
| Active |
|  |
| Expired |
|  |
| Not Public |
| English Australia - Association Online Administration |

## Abas do Evento

## Paginação

Info: Não identificada

## Próximos Passos

1. Analisar os screenshots capturados em `./legacy-events-screenshots`
2. Mapear campos do sistema legado para o Eau Events
3. Criar script de importação em batch
4. Testar com um pequeno conjunto de eventos
