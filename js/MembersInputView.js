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

		template: function(data) {
			var $el = $('<input class="member-input-field" type="text"/>');
			$el.attr('placeholder', t('customgroups', 'Add member'));
			return $el;
		},

		/**
		 * Creates a new MembersInputView
		 *
		 * @param {Object} [options]
		 */
		initialize: function(options) {
			options = options || {};

			this.collection = options.collection || new OCA.CustomGroups.ShareeCollection();
		},

		/**
		 * Autocomplete function for dropdown results
		 *
		 * @param {Object} query select2 query object
		 */
		_query: function(query) {
			this.collection.pattern = query.term;
			if (this.collection.pattern) {
				this.collection.fetch({
					success: function(collection) {
						// TODO: filter out already selected entries
						query.callback({
							results: collection.toJSON()
						});
					}
				});
			} else {
				query.callback(null);
			}
		},

		_preventDefault: function(e) {
			e.stopPropagation();
		},

		/**
		 * Renders this view
		 */
		render: function() {
			this.$el.html(this.template());

			this.$field = this.$el.find('input');
			/*
			this.$field.select2({
				placeholder: t('customgroups', 'Add member'),
				containerCssClass: 'select2-container',
				dropdownCssClass: 'select2-dropdown',
				closeOnSelect: true,
				query: _.bind(this._query, this),
				id: function(entry) {
					return entry.id;
				}
			});
			*/

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

		remove: function() {
			if (this.$field) {
				this.$field.select2('destroy');
			}
		},

		getValue: function() {
			return this.$field.val();
		},

		setValue: function(value) {
			this.$field.val(value);
		},

		autocompleteHandler: function (search, response) {
			var view = this;
			var $loading = this.$el.find('.shareWithLoading');
			$loading.removeClass('hidden');
			$loading.addClass('inlineblock');
			$.get(
				OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees',
				{
					format: 'json',
					search: search.term.trim(),
					perPage: 200,
					itemType: 'file',
					shareType: OC.Share.SHARE_TYPE_USER
				},
				function (result) {
					$loading.addClass('hidden');
					$loading.removeClass('inlineblock');
					if (result.ocs.meta.statuscode === 100) {
						var users   = result.ocs.data.exact.users.concat(result.ocs.data.users);

						// TODO: filter out existing

						var suggestions = users;
						if (suggestions.length > 0) {
							suggestions.sort(function (a, b) {
								return OC.Util.naturalSortCompare(a.label, b.label);
							});
							$('.shareWithField').removeClass('error')
								.tooltip('hide')
								.autocomplete("option", "autoFocus", true);
							response(suggestions);
						} else {
							var title = t('core', 'No users found for {search}', {search: $('.shareWithField').val()});
							$('.shareWithField').addClass('error')
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
					} else {
						response();
					}
				}
			).fail(function() {
				$loading.addClass('hidden');
				$loading.removeClass('inlineblock');
				OC.Notification.show(t('core', 'An error occurred. Please try again'));
				window.setTimeout(OC.Notification.hide, 5000);
			});
		},

		autocompleteRenderItem: function(ul, item) {

			var text = item.label;
			var insert = $("<div class='share-autocomplete-item'/>");
			var avatar = $("<div class='avatardiv'></div>").appendTo(insert);
			avatar.avatar(item.value.shareWith, 32, undefined, undefined, undefined, item.label);

			$("<div class='autocomplete-item-text'></div>")
				.text(text)
				.appendTo(insert);
			insert.attr('title', item.value.shareWith);
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
				userId: s.item.value.shareWith,
				displayName: s.item.label
			});
			$(e.target).val(s.item.value.shareWith).blur();
		}
	});

	OCA.CustomGroups = OCA.CustomGroups || {};
	OCA.CustomGroups.MembersInputView = MembersInputView;

})(OC);

