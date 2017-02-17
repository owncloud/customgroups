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

		_lastActive: null,

		events: {
			'submit form': '_onSubmitCreationForm',
			'click .select': '_onSelect'
		},

		initialize: function(collection) {
			this.collection = collection;

			this.collection.on('request', this._onRequest, this);
			this.collection.on('sync', this._onEndRequest, this);
			this.collection.on('remove', this._onRemoveGroup, this);

			_.bindAll(this, '_onSubmitCreationForm');
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

		/**
		 * Select by group id or model.
		 *
		 * @param {String|OCA.CustomGroups.CustomGroupModel} group group id or group model
		 * @param {Object} [$el] optional DOM element of the entry
		 */
		select: function(group, $el) {
			var model = group;
			if (_.isString(model)) {
				model = this.collection.findWhere({'id': model});
			}

			if (model) {
				if (this._lastActive) {
					this._lastActive.removeClass('active');
				}

				if (_.isUndefined($el)) {
					$el = this.$el.find('li').filterAttr('data-id', model.id);
				}
				this._lastActive = $el.addClass('active');
				this.trigger('select', model);
			}
		},

		_onSelect: function(ev) {
			ev.preventDefault();
			var $el = $(ev.target).closest('li');
			var id = $el.attr('data-id');
			this.select(id, $el);
		},

		/**
		 * Attempt creating a group with the given name.
		 * If the uri already exists, try another one.
		 *
		 * @param {string} groupName group display name
		 */
		_createGroup: function(groupName, index) {
			var self = this;
			// TODO: check if the current user already has a group with this name

			// it might happen that a group uri already exists for that name,
			// so attempt multiple ones
			this.collection.create({
				id: this._formatUri(groupName, index),
				displayName: groupName,
				role: OCA.CustomGroups.ROLE_ADMIN
			}, {
				wait: true,
				success: function(model) {
					self.select(model);
				},
				error: function(model, response) {
					if (response.status === 405) {
						if (_.isUndefined(index)) {
							// attempt again with index
							index = 1;
						}
						self._createGroup(groupName, index + 1);
						return;
					}
					OC.Notification.showTemporary(t('customgroups', 'Could not create group'));
				}
			});
		},

		_onSubmitCreationForm: function(ev) {
			ev.preventDefault();
			var groupName = this.$el.find('[name=groupName]').val();

			this._createGroup(groupName);
			return false;
		},

		_onRemoveGroup: function(model, collection, options) {
			var $groupEl = this.$el.find('ul li:nth-child(' + (options.index + 1) + ')');
			if ($groupEl.hasClass('active')) {
				// deselect
				this.trigger('select', null);
				this._lastActive = null;
			}
			$groupEl.remove();
			if (!collection.length) {
				// rerender empty list
				this.render();
			}
		},

		/**
		 * Converts a group display name to a valid URI name
		 *
		 * @param {string} groupName display name
		 * @return {string} group URI
		 */
		_formatUri: function(groupName, index) {
			// TODO: strip unwanted chars
			if (!_.isUndefined(index)) {
				return groupName + index;
			}
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

