/*
 * Copyright (c) 2017
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OC) {
	/**
	 * @class OC.SystemTags.MembersInputView
	 * @classdesc
	 *
	 * Displays a file's system tags
	 *
	 */
	var MembersInputView = OC.Backbone.View.extend(
		/** @lends OCA.CustomGroups.MembersInputView.prototype */ {

		_rendered: false,

		_newTag: null,

		className: 'member-input-view',

		template: function() {
			var $el = $('<div></div>');
			$el.append($('<input class="member-input-field" type="text"/>')
				.attr('placeholder', t('customgroups', 'Add user to this group')));
			$el.append('<span class="loading icon-loading-small hidden"></span>');
			return $el;
		},

		/**
		 * Creates a new MembersInputView
		 *
		 * @param {Object} [options]
		 */
		initialize: function(options) {
			options = options || {};

			this.groupUri = options.groupUri;
		},

		/**
		 * Renders this view
		 */
		render: function() {
			this.$el.html(this.template());

			this.$field = this.$('input');
			this.$field.autocomplete({
				minLength: 1,
				delay: 750,
				focus: function(event) {
					event.preventDefault();
				},
				source: _.bind(this.autocompleteHandler, this),
				select: _.bind(this._onSelect, this)
			}).data('ui-autocomplete')._renderItem = _.bind(this.autocompleteRenderItem, this);

			this.delegateEvents();
		},

		getValue: function() {
			return this.$field.val();
		},

		setValue: function(value) {
			this.$field.val(value);
		},

		autocompleteHandler: function (search, response) {
			var self = this;
			var $loading = this.$el.find('.loading');
			this.$field.tooltip('hide');
			$loading.removeClass('hidden');
			$loading.addClass('inlineblock');
			$.ajax({
				url: OC.generateUrl('/apps/customgroups/members'),
				contentType: 'application/json',
				dataType: 'json',
				data: {
					group: this.groupUri,
					pattern: search.term.trim(),
					limit: 200
				}
			}).done(function (result) {
					$loading.addClass('hidden');
					$loading.removeClass('inlineblock');

					if (result.message) {
						OC.Notification.showTemporary(result.message);
						response();
						return;
					}

					result = result.results;

					if (result.length > 0) {
						result.sort(function (a, b) {
							return OC.Util.naturalSortCompare(a.displayName, b.displayName);
						});
						self.$field.removeClass('error')
							.tooltip('hide')
							.autocomplete("option", "autoFocus", true);
						response(result);
					} else {
						var title = t('core', 'No users found for {search}', {search: self.$field.val()});
						self.$field.addClass('error')
							.attr('data-original-title', title)
							.tooltip('hide')
							.tooltip({
								placement: 'bottom',
								trigger: 'manual'
							})
							.tooltip('fixTitle')
							.tooltip('show');
						response();
					}
				}
			).fail(function() {
				$loading.addClass('hidden');
				$loading.removeClass('inlineblock');
				OC.Notification.showTemporary(t('core', 'An error occurred. Please try again'));
			});
		},

		autocompleteRenderItem: function(ul, item) {
			var text = item.displayName;
			var insert = $("<div class='customgroups-autocomplete-item'/>");
			var avatar = $("<div class='avatardiv'></div>").appendTo(insert);
			avatar.avatar(item.userId, 32, undefined, undefined, undefined, item.displayName);

			$("<div class='autocomplete-item-text'></div>")
				.text(text)
				.appendTo(insert);
			insert.attr('title', item.userId);
			insert = $("<a>")
				.append(insert);
			return $("<li>")
				.addClass('user')
				.append(insert)
				.appendTo(ul);
		},

		_onSelect: function(e, s) {
			e.preventDefault();
			this.trigger('select', {
				userId: s.item.userId,
				displayName: s.item.displayName
			});
			$(e.target).val(s.item.userId).blur();
		}
	});

	OCA.CustomGroups = OCA.CustomGroups || {};
	OCA.CustomGroups.MembersInputView = MembersInputView;

})(OC);

