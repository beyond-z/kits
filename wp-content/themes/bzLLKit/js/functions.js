/**
 * 
 */

( function( $ ) {
	console.log('jquery running');
	
	/* Expand or collapse individual activities */
	$('.activity header').click(function(e){
		e.preventDefault();
		$(this).toggleClass('collapsed').siblings().slideToggle(250);
	});
	
	/* Expand or collapse all activities */
	$('#activity-plan-header').append('<a class="btn" id="expand-collapse-all-btn" title="Collapse all activities" href="#activity-plan-header">Expand/collapse all</a>').click(function(e){
		e.preventDefault();
		if ($(this).hasClass('all-collapsed')) {
			$('.activity header').removeClass('collapsed').siblings().slideDown(250);
			$(this).removeClass('all-collapsed');
		} else {
			$(this).addClass('all-collapsed');
			$('.activity header').addClass('collapsed').siblings().slideUp(250);
		}
	}).click();
	
	/* Show/hide space for notes
	$('#activity-plan-header').append('<a class="btn" id="add-notes-btn" title="Show or hide space for notes" href="#activity-plan-header">Show/hide notes</a>').click(function(e){
		e.preventDefault();
		$('.sub-activities').toggleClass('with-notes');
	});
	/**/

} )( jQuery );
