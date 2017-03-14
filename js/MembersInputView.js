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
			return '<input class="member-input-field" type="hidden" />';
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
			this.$field.select2('val', value);
		}
	});

	OCA.CustomGroups = OCA.CustomGroups || {};
	OCA.CustomGroups.MembersInputView = MembersInputView;

})(OC);

