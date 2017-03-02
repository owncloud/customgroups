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
	 * @class OCA.CustomGroups.MembersView
	 * @classdesc
	 *
	 */
	var MembersView = OC.Backbone.View.extend(
		/** @lends OCA.CustomGroups.MembersView.prototype */ {
		sync: OC.Backbone.davSync,

		events: {
			'submit form': '_onSubmitCreationForm',
			'click .action-delete-member': '_onDeleteMember',
			'click .action-change-member-role': '_onChangeMemberRole',
			'click .action-leave-group': '_onClickLeaveGroup',
			'click .load-more': '_onClickLoadMore'
		},

		initialize: function(model) {
			this.model = model;
			this.collection = model.getChildrenCollection();

			this.collection.on('request', this._onRequest, this);
			this.collection.on('sync', this._onEndRequest, this);
			this.collection.on('add', this._onAddModel, this);
			this.collection.on('change', this._onChangeModel, this);
			this.collection.on('remove', this._onRemoveMember, this);

			this.model.on('change:displayName', this._renderHeader, this);

			this.collection.reset([], {silent: true});
			this.collection.fetchNext();

			_.bindAll(
				this,
				'_onSubmitCreationForm',
				'_onDeleteMember',
				'_onChangeMemberRole',
				'_onClickLeaveGroup'
			);
		},

		remove: function() {
			this.collection.off('request', this._onRequest, this);
			this.collection.off('sync', this._onEndRequest, this);
			this.collection.off('add', this._onAddModel, this);
			this.collection.off('change', this._onChangeModel, this);
			this.collection.off('remove', this._onRemoveMember, this);
			this.model.off('change:displayName', this._renderHeader, this);

			this.$el.remove();
		},

		_toggleLoading: function(state) {
			this._loading = state;
			this.$('.loading').toggleClass('hidden', !state);
		},

		_onRequest: function() {
			this._toggleLoading(true);
			this.$('.empty').addClass('hidden');
			this.$('.load-more').addClass('hidden');
		},

		_onEndRequest: function() {
			this._toggleLoading(false);
			this.$('.empty').toggleClass('hidden', !!this.collection.length);
			this.$('.load-more').toggleClass('hidden', this.collection.endReached);
		},

		_onClickLoadMore: function(ev) {
			ev.preventDefault();
			this.collection.fetchNext();
 		},

		_onClickLeaveGroup: function() {
			var currentUserMembership = this.collection.get(OC.getCurrentUser().uid);
			if (window.confirm('Confirm leaving of group ' + this.model.get('displayName') + ' ?')) {
				currentUserMembership.destroy({
					wait: true,
					error: function(model, response) {
						if (response.status === 403) {
							OC.Notification.showTemporary(t('customgroups', 'Cannot leave group without an administrator'));
						} else {
							OC.Notification.showTemporary(t('customgroups', 'Could not leave group due to a server error'));
						}
					}
				});
			}
		},

		_onAddModel: function(model, collection, options) {
			var $el = $(this.itemTemplate(this._formatMember(model)));
			if (!_.isUndefined(options.index) && collection.length > 1) {
				this.$container.find('.group-member').eq(options.index).before($el);
			} else {
				this.$container.append($el);
			}
			this._postProcessRow($el, model);
		},

		_onChangeModel: function(model) {
			var $el = $(this.itemTemplate(this._formatMember(model)));
			var index = this.collection.indexOf(model);
			this.$container.children().eq(index).replaceWith($el);
			this._postProcessRow($el, model);
		},

		_postProcessRow: function($el, model) {
			$el.find('[title]').tooltip();
			$el.find('.avatar').avatar(model.id, 32);
		},

		_onRemoveMember: function(model, collection, options) {
			var $memberEl;
			if (_.isNumber(options.index)) {
				$memberEl = this.$('.grid .group-member:nth-child(' + (options.index + 1) + ')');
			} else {
				$memberEl = this.$('.grid .group-member').filterAttr('data-id', model.id);
			}
			$memberEl.remove();
		},

		_onSubmitCreationForm: function(ev) {
			ev.preventDefault();
			var $field = this.$('[name=memberUserId]');
			var userId = $field.val();

			if (!userId) {
				return false;
			}

			this.collection.create({
				id: userId
			},  {
				wait: true,
				success: function() {
					$field.val('');
				},
				error: function(model, response) {
					if (response.status === 412) {
						OC.Notification.showTemporary(t(
							'customgroups',
							'User "{userId}" does not exist',
							{userId: userId}
						));
					} else if (response.status === 409) {
						OC.Notification.showTemporary(t(
							'customgroups',
							'User "{userId}" is already a member of this group',
							{userId: userId}
						));
					} else {
						OC.Notification.showTemporary(t(
							'customgroups',
							'Could not add user to group'
						));
					}
				}
			});
			return false;
		},

		_onDeleteMember: function(ev) {
			ev.preventDefault();
			var $row = $(ev.target).closest('.group-member');
			var id = $row.attr('data-id');

			// deleting self ?
			if (id === OC.getCurrentUser().uid) {
				return this._onClickLeaveGroup();
			}

			var model = this.collection.findWhere({'id': id});

			if (!model) {
				return;
			}

			// TODO: use undo approach
			if (window.confirm('Confirm deletion of member ' + model.id + ' ?')) {
				model.destroy();
			}
		},

		_onChangeMemberRole: function(ev) {
			ev.preventDefault();
			var $row = $(ev.target).closest('.group-member');
			var id = $row.attr('data-id');

			// changing own permissions ?
			if (id === OC.getCurrentUser().uid) {
				if (!window.confirm('Remove your admin powers ?')) {
					return;
				}
			}

			var model = this.collection.findWhere({'id': id});

			if (!model) {
				return;
			}

			// swap permissions
			var newRole = (model.get('role') === OCA.CustomGroups.ROLE_ADMIN) ?
				OCA.CustomGroups.ROLE_MEMBER :
				OCA.CustomGroups.ROLE_ADMIN;

			var self = this;
			var index = $row.index();
			model.save({
				role: newRole
			});
		},

		_formatMember: function(member) {
			return {
				id: member.id,
				displayName: member.id,
				canAdmin: OC.isUserAdmin() || this.model.get('role') === OCA.CustomGroups.ROLE_ADMIN,
				roleDisplayName: (member.get('role') === OCA.CustomGroups.ROLE_ADMIN) ?
					t('customgroups', 'Group admin') :
					t('customgroups', 'Member')
			};
		},

		template: function(data) {
			return OCA.CustomGroups.Templates.membersList(data);
		},

		headerTemplate: function(data) {
			return OCA.CustomGroups.Templates.membersListHeader(data);
		},

		itemTemplate: function(data) {
			return OCA.CustomGroups.Templates.membersListItem(data);
		},

		_renderHeader: function() {
			var data = {
				displayNameLabel: t('customgroups', 'Display name'),
				newMemberPlaceholder: t('customgroups', 'Add member'),
				newMemberSubmitLabel: t('customgroups', 'Add member'),
				leaveGroupLabel: t('customgroups', 'Leave this group'),
				closeLabel: t('customgroups', 'Close'),
				// super admin might not be member
				// not having a role means not being a member
				userIsMember: !_.isUndefined(this.model.get('role')),
				canAdmin: OC.isUserAdmin() || this.model.get('role') === OCA.CustomGroups.ROLE_ADMIN
			};
			if (this.model) {
				data.groupName = this.model.get('displayName');
			}
			var $header = this.$('.header');
			$header.html(this.headerTemplate(data));
			$header.find('.avatar').imageplaceholder(
				// combine uri with display name for seed
				this.model.id + ':' + this.model.get('displayName'),
				this.model.get('displayName'),
				32
			);
		},

		render: function() {
			this.$el.html(this.template());
			this.$('[title]').tooltip();
			this.$container = this.$('.group-member-list');
			this._renderHeader();
			this.delegateEvents();
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.MembersView = MembersView;

})(OC, OCA);

