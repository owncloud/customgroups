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
			'click .action-rename-group': '_onRenameGroup',
			'click .action-delete-group': '_onDeleteGroup',
			'click .action-delete-member': '_onDeleteMember',
			'click .action-change-member-role': '_onChangeMemberRole',
			'click .action-leave-group': '_onClickLeaveGroup'
		},

		initialize: function(model) {
			this.model = model;
			this.collection = model.getChildrenCollection();

			this.collection.on('request', this._onRequest, this);
			this.collection.on('sync', this._onEndRequest, this);
			this.collection.on('remove', this._onRemoveMember, this);

			// TODO: only rerender header
			this.model.on('change:displayName', this.render, this);

			this.collection.fetch();

			_.bindAll(
				this,
				'_onSubmitCreationForm',
				'_onRenameGroup',
				'_onDeleteGroup',
				'_onDeleteMember',
				'_onChangeMemberRole',
				'_onClickLeaveGroup'
			);
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

		_onClickLeaveGroup: function() {
			var currentUserMembership = this.collection.get(OC.getCurrentUser().uid);
			if (confirm('Confirm leaving of group ' + this.model.get('displayName') + ' ?')) {
				currentUserMembership.destroy({
					wait: true,
					error: function() {
						OC.Notification.showTemporary(t('customgroups', 'Cannot leave group without an administrator'));
					}
				});
			}
		},

		_onRenameGroup: function(ev) {
			ev.preventDefault();

			// TODO: use inline rename form
			var newName = prompt('Enter new name', this.model.get('displayName'));
			if (newName) {
				// TODO: lock row during save
				this.model.save({
					displayName: newName
				});
			}
			return false;
		},

		_onDeleteGroup: function(ev) {
			ev.preventDefault();
			// TODO: use undo approach
			if (confirm('Confirm deletion of group ' + this.model.get('displayName') + ' ?')) {
				this.model.destroy({
					wait: true
				});
			}
		},

		_onRemoveMember: function(model, collection, options) {
			this.$el.find('.grid tr:nth-child(' + (options.index + 1) + ')').remove();
		},

		_onSubmitCreationForm: function(ev) {
			ev.preventDefault();
			var $field = this.$el.find('[name=memberUserId]');
			var userId = $field.val();

			if (!userId) {
				return;
			}

			this.collection.create({
				id: userId
			},  {
				wait: true,
				success: function() {
					_.defer(function() {
						$field.focus();
					});
				},
				error: function(model, response) {
					if (response.status === 412) {
						OC.Notification.showTemporary(t('customgroups', 'User "{userId}" does not exist', {userId: userId}));
						$field.focus();
					} else if (response.status === 409) {
						OC.Notification.showTemporary(t('customgroups', 'User "{userId}" is already a member of this group', {userId: userId}));
						$field.focus();
					}
				}
			});
			return false;
		},

		_onDeleteMember: function(ev) {
			ev.preventDefault();
			var $row = $(ev.target).closest('tr');
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
			if (confirm('Confirm deletion of member ' + model.id + ' ?')) {
				model.destroy();
			}
		},

		_onChangeMemberRole: function(ev) {
			ev.preventDefault();
			var $row = $(ev.target).closest('tr');
			var id = $row.attr('data-id');

			// changing own permissions ?
			if (id === OC.getCurrentUser().uid) {
				if (!confirm('Remove your admin powers ?')) {
					return;
				}
			}

			var model = this.collection.findWhere({'id': id});

			if (!model) {
				return;
			}

			// TODO: confirmation ?
			// TODO: for now we just swap the permission
			var newRole = (model.get('role') === OCA.CustomGroups.ROLE_ADMIN) ?
				OCA.CustomGroups.ROLE_MEMBER :
				OCA.CustomGroups.ROLE_ADMIN;

			model.save({
				role: newRole
			});
		},

		_formatMember: function(member) {
			return {
				id: member.id,
				displayName: member.id,
				roleDisplayName: (member.get('role') === OCA.CustomGroups.ROLE_ADMIN) ?
					t('customgroups', 'Group admin') :
					t('customgroups', 'Member')
			};
		},

		template: function(data) {
			return OCA.CustomGroups.Templates.membersList(data);
		},

		render: function() {
			var isSuperAdmin = OC.isUserAdmin();
			var data = {
				pageTitle: t('customgroups', 'Group members'),
				renameLabel: t('customgroups', 'Rename group'),
				deleteLabel: t('customgroups', 'Delete group'),
				displayNameLabel: t('customgroups', 'Display name'),
				newMemberPlaceholder: t('customgroups', 'Add member'),
				newMemberSubmitLabel: t('customgroups', 'Add member'),
				leaveGroupLabel: t('customgroups', 'Leave this group'),
				deleteMemberLabel: t('customgroups', 'Remove from group'),
				empty: !this.collection.length,
				emptyMessage: t('customgroups', 'There are currently no members in this group'),
				members: this.collection.map(this._formatMember),
				// super admin might not be member
				// not having a role means not being a member
				userIsMember: !_.isUndefined(this.model.get('role')),
				canAdmin: isSuperAdmin || this.model.get('role') === OCA.CustomGroups.ROLE_ADMIN
			};
			if (this.model) {
				data.groupName = this.model.get('displayName');
			}

			this.$el.html(this.template(data));
			this.$el.find('[title]').tooltip();
			this.delegateEvents();
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.MembersView = MembersView;

})(OC, OCA);

