/**
 * 
 */

( function( $ ) {
	console.log('jquery running');
	
	// Expand or collapse individual activities
	$('.activity header').click(function(e){
		e.preventDefault();
		$(this).toggleClass('collapsed').siblings().slideToggle(250);
	});
	
	// Expand or collapse all activities 
	$('#activity-plan-header').append('<a class="btn" id="collapse-all-btn" title="Collapse all activities" href="#activity-plan-header">Collapse all</a><a class="btn" id="expand-all-btn" title="Expand all activities" href="#activity-plan-header">Expand all</a>');
	$("#collapse-all-btn").click(function(e){
		e.preventDefault();
		$('.activity header').addClass('collapsed').siblings().slideUp(250);
	});
	$("#expand-all-btn").click(function(e){
		e.preventDefault();
		$('.activity header').removeClass('collapsed').siblings().slideDown(250);
	});
	
	// Show/hide space for notes
	$('#activity-plan-header').append('<a class="btn" id="add-notes-btn" title="Show or hide space for notes" href="#activity-plan-header">Show/hide notes</a>').click(function(e){
		e.preventDefault();
		$('.sub-activities').toggleClass('with-notes');
	});
	

} )( jQuery );
