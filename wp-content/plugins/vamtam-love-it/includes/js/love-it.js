(function($, undefined) {
	'use strict';

	$(function() {
		if(!love_it_vars.logged_in) {
			$('.love-it').each(function() {
				if( $.cookie('loved-' + $(this).data('post-id')) ) {
					$(this).parent().prepend( '<span class="loved"><span class="loved-text">' + love_it_vars.already_loved_message + '</span>');
					$(this).remove();
				}
			});
		}

		$('body').on('click', '.love-it', function() {
			var $this = $(this);

			var post_id = $this.data('post-id');
			var user_id = $this.data('user-id');
			var post_data = {
				action: 'love_it',
				item_id: post_id,
				user_id: user_id,
				love_it_nonce: love_it_vars.nonce
			};

			if($this.hasClass('loved') || (!love_it_vars.logged_in && $.cookie('loved-' + post_id))) {
				alert(love_it_vars.already_loved_message);
				return false;
			}

			$.post(love_it_vars.ajaxurl, post_data, function(response) {
				if(response === 'loved') {
					$this.addClass('loved');
					var count_wrap = $this.next();
					var count = count_wrap.text();
					count_wrap.text(parseInt(count, 10) + 1);

					if(!love_it_vars.logged_in) {
						$.cookie('loved-' + post_id, 'yes', { expires: 1 });
					}
				} else {
					alert(love_it_vars.error_message);
				}
			});
			return false;
		});
	});
})(jQuery);