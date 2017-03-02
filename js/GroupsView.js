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
	 * @class OCA.CustomGroups.GroupsView
	 * @classdesc
	 *
	 */
	var GroupsView = OC.Backbone.View.extend(
		/** @lends OCA.CustomGroups.GroupsView.prototype */ {
		sync: OC.Backbone.davSync,

		_lastActive: null,

		events: {
			'submit form': '_onSubmitCreationForm',
			'click .select': '_onSelect',
			'click .action-rename-group': '_onRenameGroup',
			'click .action-delete-group': '_onDeleteGroup',
			'click .load-more': '_onClickLoadMore'
		},

		initialize: function(collection) {
			this.collection = collection;

			this.collection.on('request', this._onRequest, this);
			this.collection.on('sync', this._onEndRequest, this);
			this.collection.on('add', this._onAddModel, this);
			this.collection.on('change', this._onChangeModel, this);
			this.collection.on('remove', this._onRemoveGroup, this);
		},

		_toggleLoading: function(state) {
			this._loading = state;
			this.$('.loading').toggleClass('hidden', !state);
		},

		_findGroupModelForEvent: function(ev) {
			var $el = $(ev.target).closest('.group');
			var id = $el.attr('data-id');
			return this.collection.findWhere({'id': id});
		},

		_onRenameGroup: function(ev) {
			ev.preventDefault();

			var model = this._findGroupModelForEvent(ev); 
			if (!model) {
				return false;
			}

			// TODO: use inline rename form
			var newName = window.prompt('Enter new name', model.get('displayName'));
			if (newName) {
				// TODO: lock row during save
				model.save({
					displayName: newName
				});
			}
			return false;
		},

		_onDeleteGroup: function(ev) {
			ev.preventDefault();

			var model = this._findGroupModelForEvent(ev); 
			if (!model) {
				return false;
			}

			// TODO: use undo approach
			if (window.confirm('Confirm deletion of group ' + model.get('displayName') + ' ?')) {
				model.destroy({
					wait: true
				});
			}
			return false;
		},

		_onRequest: function() {
			this._toggleLoading(true);
			this.$('.empty').addClass('hidden');
			this.$('.load-more').addClass('hidden');
		},

		_onEndRequest: function() {
			this._toggleLoading(false);
			this._updateEmptyState();
			this.$('.load-more').toggleClass('hidden', this.collection.endReached);
		},

		_onClickLoadMore: function(ev) {
			ev.preventDefault();
			this.collection.fetchNext();
 		},

		_updateEmptyState: function() {
			this.$('.empty').toggleClass('hidden', !!this.collection.length);
			this.$('.grid').toggleClass('hidden', !this.collection.length);
		},

		/**
		 * Select by group id or model.
		 *
		 * @param {String|OCA.CustomGroups.GroupModel} group group id or group model
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
					$el = this.$('.group').filterAttr('data-id', model.id);
				}
				this._lastActive = $el.addClass('active');
				this.trigger('select', model);
			}
		},

		_onSelect: function(ev) {
			ev.preventDefault();
			var $el = $(ev.target).closest('.group');
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
					self.$('[name=groupName]').val('');
					self.select(model);
				},
				error: function(model, response) {
					// stop at 100 to avoid running for too long...
					if (response.status === 405 && (_.isUndefined(index) || index < 100)) {
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
			var groupName = this.$('[name=groupName]').val();

			if (groupName) {
				this._createGroup(groupName);
			}
			return false;
		},

		_onAddModel: function(model, collection, options) {
			var $el = $(this.itemTemplate(this._formatItem(model)));
			if (!_.isUndefined(options.at) && collection.length > 1) {
				this.$container.find('.group').eq(options.at).before($el);
			} else {
				this.$container.append($el);
			}
			this._postProcessRow($el, model);
			this._updateEmptyState();
		},

		_onChangeModel: function(model) {
			var $el = $(this.itemTemplate(this._formatItem(model)));
			var index = this.collection.indexOf(model);
			this.$container.children().eq(index).replaceWith($el);
			this._postProcessRow($el, model);
		},


		_postProcessRow: function($el, model) {
			$el.find('[title]').tooltip();
			// no group avatars, so using "imageplaceholder" directly instead of "avatar"
			$el.find('.avatar').imageplaceholder(
				// combine uri with display name for seed
				model.id + ':' + model.get('displayName'),
				model.get('displayName'),
				32
			);
		},

		_onRemoveGroup: function(model, collection, options) {
			var $groupEl;
			if (_.isNumber(options.index)) {
				$groupEl = this.$('.group-list .group:nth-child(' + (options.index + 1) + ')');
			} else {
				// less efficient
				$groupEl = this.$('.group-list .group').filterAttr('data-id', model.id);
			}
			if ($groupEl.hasClass('active')) {
				// deselect
				this.trigger('select', null);
				this._lastActive = null;
			}
			$groupEl.remove();
			if (!collection.length) {
				this._updateEmptyState();
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

		_formatItem: function(group) {
			return {
				id: group.id,
				displayName: group.get('displayName'),
				renameLabel: t('customgroups', 'Rename'),
				deleteLabel: t('customgroups', 'Delete'),
				canAdmin: OC.isUserAdmin() || group.get('role') === OCA.CustomGroups.ROLE_ADMIN,
				roleDisplayName: (group.get('role') === OCA.CustomGroups.ROLE_ADMIN) ?
					t('customgroups', 'Group admin') :
					t('customgroups', 'Member')
			};
		},

		template: function(data) {
			return OCA.CustomGroups.Templates.list(data);
		},

		itemTemplate: function(data) {
			return OCA.CustomGroups.Templates.listItem(data);
		},

		render: function() {
			this.$el.html(this.template({
				displayNameLabel: t('customgroups', 'Display name'),
				newGroupPlaceholder: t('customgroups', 'Group name'),
				newGroupSubmitLabel: t('customgroups', 'Create group'),
				groupLabel: t('customgroups', 'Group'),
				yourRoleLabel: t('customgroups', 'Your role'),
				emptyMessage: t('customgroups', 'There are currently no user defined groups'),
+				loadMoreLabel: t('customgroups', 'Load more')
			}));
			this.$container = this.$('.group-list');
			this.delegateEvents();
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.GroupsView = GroupsView;

})(OC, OCA);

