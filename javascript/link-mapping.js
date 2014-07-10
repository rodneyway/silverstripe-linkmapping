/*
	This functionality has been duplicated using the initial implementation from https://github.com/nglasl/silverstripe-apiwesome
	@author Nathan Glasl <nathan@silverstripe.com.au>
 */

;(function($) {
	$(function() {

		// Determine whether the test URL button display and functionality should be enabled.

		function enable(input) {

			var URL = input ? input.val() : $('div.link-mapping-test.admin input.url').val();
			var button = $('div.link-mapping-test.admin span.test');
			if(URL.length > 0) {
				button.fadeTo(250, 1, function() {
					button.removeClass('disabled');
				});
			}
			else {
				button.fadeTo(250, 0.4, function() {
					button.addClass('disabled');
				});
			}
		};

		// Bind the mouse events dynamically.

		$.entwine('ss', function($) {

			// Trigger an interface update on key press.

			$('div.link-mapping-test.admin input.url').entwine({
				onchange: function() {

					enable($(this));
				}
			});

			// Trigger an interface update and handle any preview request.

			$('div.link-mapping-test.admin span.test').entwine({
				onmouseenter: function() {

					enable();
				},
				onclick: function() {

					if(!$(this).hasClass('disabled')) {
					}
				}
			});
		});

	});
})(jQuery);
