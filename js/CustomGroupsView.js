/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OC, OCA) {

	/**
	 * @class OCA.CustomGroups.CustomGroupsView
	 * @classdesc
	 *
	 */
	var CustomGroupsView = OC.Backbone.View.extend(
		/** @lends OCA.CustomGroups.CustomGroupsView.prototype */ {
		sync: OC.Backbone.davSync,

		events: {
			'submit form': '_onSubmitCreationForm',
			'click .action-rename': '_onRename'
		},

		initialize: function(collection) {
			this.collection = collection;

			this.collection.on('request', this._onRequest, this);
			this.collection.on('sync', this._onEndRequest, this);

			_.bindAll(this, '_onSubmitCreationForm', '_onRename');
		},

		_toggleLoading: function(state) {
			this._loading = state;
			this.$el.find('.loading').toggleClass('hidden', !state);
		},

		_onRequest: function() {
			this._toggleLoading(true);
		},

		_onEndRequest: function() {
			this._toggleLoading(false);
			this.$el.find('.empty').toggleClass('hidden', !!this.collection.length);

			this.render();
		},

		_onRename: function(ev) {
			ev.preventDefault();
			var id = $(ev.target).closest('tr').attr('data-id');
			var model = this.collection.findWhere({'id': id});
	
			// TODO: use inline rename form
			var newName = prompt('Enter new name', model.get('displayName'));
			if (newName) {
				// TODO: lock row during save
				model.save({displayName: newName});
			}
			return false;
		},

		_onSubmitCreationForm: function(ev) {
			ev.preventDefault();
			var groupName = this.$el.find('[name=groupName]').val();
			var model = new OCA.CustomGroups.CustomGroupModel({
				uri: this._formatUri(groupName),
				displayName: groupName
			});
			model.save();
			return false;
		},

		/**
		 * Converts a group display name to a valid URI name
		 *
		 * @param {string} groupName display name
		 * @return {string} group URI
		 */
		_formatUri: function(groupName) {
			// TODO: strip unwanted chars
			return groupName;
		},

		template: function(data) {
			return OCA.CustomGroups.Templates.list(data);
		},

		render: function() {
			this.$el.html(this.template({
				pageTitle: t('customgroups', 'User defined groups'),
				displayNameLabel: t('customgroups', 'Display name'),
				newGroupPlaceholder: t('customgroups', 'Group name'),
				newGroupSubmitLabel: t('customgroups', 'Create group'),
				renameLabel: t('customgroups', 'Rename'),
				deleteLabel: t('customgroups', 'Delete'),
				emptyMessage: t('customgroups', 'There are currently no user defined groups'),
				groups: this.collection.toJSON(),
				empty: !this.collection.length
			}));
			this.delegateEvents();
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.CustomGroupsView = CustomGroupsView;

})(OC, OCA);

