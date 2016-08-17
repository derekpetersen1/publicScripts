$(document).ready(function() {

	$('select.matchSelect').on('change', function(e) {
		var value = $(this).val();
		var id = $(this).attr('id');
		var image = $('#' + id + '_image');

		var allSelects = $('select.matchSelect').map(function(){
			return this.value;
		}).get();
		
		var duplicateCheck = [];
		var count = 0;
		for (var x = 0; x < allSelects.length; x++) {
			if ($.inArray(allSelects[x], duplicateCheck) != -1 && allSelects[x] != "NotMatched") {
				count++;
			}
			duplicateCheck.push(allSelects[x]);
		}
		
		if (count > 0 && value != "NotMatched") {
			$("#" + id + " option[value='NotMatched']").attr('selected', true);
			$(this).val('NotMatched');
			$('#' + id + '_image').prop('src', '../imgs/redX.png');
			$('#' + id + '_image').prop('title', 'Not Matched');
		} else {
			if (value != "NotMatched") {
				$('#' + id + '_image').prop('src', '../imgs/greenCheckmark.png');
				$('#' + id + '_image').prop('title', 'User Matched');
			} else {
				$('#' + id + '_image').prop('src', '../imgs/redX.png');
				$('#' + id + '_image').prop('title', 'Not Matched');
			}
		}

	}); 
	
});

	