/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('MembersView test', function() {
	var view;
	var collection;
	var model;

	var imageplaceholderStub;
	var avatarStub;

	var currentUserStub;

	beforeEach(function() {
		imageplaceholderStub = sinon.stub($.fn, 'imageplaceholder');
		avatarStub = sinon.stub($.fn, 'avatar');
		currentUserStub = sinon.stub(OC, 'getCurrentUser').returns({uid: 'currentUser'});

		model = new OCA.CustomGroups.GroupModel({
			id: 'group1',
			displayName: 'Group One',
			role: OCA.CustomGroups.ROLE_ADMIN
		});
		collection = model.getChildrenCollection();
		view = new OCA.CustomGroups.MembersView(model);
	});
	afterEach(function() {
		view.remove();
		view = null;
		collection = null;

		imageplaceholderStub.restore();
		avatarStub.restore();
		currentUserStub.restore();
	});

	describe('rendering', function() {
		beforeEach(function() {
			view.render();
		});
		
		it('renders empty list at first', function() {
			expect(view.$('.group-member-list').length).toBeDefined();
		});

		it('renders header', function() {
			expect(view.$('.group-name-title-display').text()).toEqual('Group One');

			expect(imageplaceholderStub.calledOnce).toEqual(true);
			expect(imageplaceholderStub.getCall(0).thisValue.get(0))
				.toEqual(view.$('.group-name-title .avatar').get(0));
			expect(imageplaceholderStub.getCall(0).args[0]).toEqual('group1:Group One');
			expect(imageplaceholderStub.getCall(0).args[1]).toEqual('Group One');
		});

		it('rerenders header if group display name changed', function() {
			model.set({displayName: 'Group Renamed'});
			expect(view.$('.group-name-title-display').text()).toEqual('Group Renamed');
		});

		it('renders members as they are added', function() {
			collection.add([{
				id: 'user1',
				userDisplayName: 'User One',
				role: OCA.CustomGroups.ROLE_ADMIN
			}, { 
				id: 'user2',
				userDisplayName: 'User Two',
				role: OCA.CustomGroups.ROLE_MEMBER
			}]);

			expect(view.$('.group-member').length).toEqual(2);
			expect(view.$('.group-member:eq(0) .user-display-name').text()).toEqual('User One');
			expect(view.$('.group-member:eq(0) .role-display-name').text()).toEqual(t('customgroups', 'Group owner'));
			expect(view.$('.group-member:eq(1) .user-display-name').text()).toEqual('User Two');
			expect(view.$('.group-member:eq(1) .role-display-name').text()).toEqual(t('customgroups', 'Member'));

			// avatar
			expect(avatarStub.calledTwice).toEqual(true);
			expect(avatarStub.getCall(0).thisValue.get(0)).toEqual(view.$('.group-member:eq(0) .avatar').get(0));
			expect(avatarStub.getCall(0).args[0]).toEqual('user1');
			expect(avatarStub.getCall(0).args[1]).toEqual(32);
			expect(avatarStub.getCall(1).thisValue.get(0)).toEqual(view.$('.group-member:eq(1) .avatar').get(0));
			expect(avatarStub.getCall(1).args[0]).toEqual('user2');
			expect(avatarStub.getCall(1).args[1]).toEqual(32);
		});

		it('renders admin actions when user\'s role in group is group owner', function() {
			model.set({
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			collection.add({
				id: 'user1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			expect(view.$('.group-member:eq(0) .action-change-member-role').length).toEqual(1);
			expect(view.$('.group-member:eq(0) .action-delete-member').length).toEqual(1);
		});
		it('does not renders admin actions when role is member', function() {
			model.set({
				role: OCA.CustomGroups.ROLE_MEMBER
			});
			collection.add({
				id: 'user2',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			expect(view.$('.group-member:eq(0) .action-change-member-role').length).toEqual(0);
			expect(view.$('.group-member:eq(0) .action-delete-member').length).toEqual(0);
		});

		it('rerenders row when member role changed', function() {
			var group1 = collection.add({
				id: 'user1',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			collection.add({
				id: 'user2',
				role: OCA.CustomGroups.ROLE_MEMBER
			});

			group1.set({
				role: OCA.CustomGroups.ROLE_MEMBER
			});

			expect(view.$('.group-member').length).toEqual(2);
			expect(view.$('.group-member:eq(0) .role-display-name').text()).toEqual(t('customgroups', 'Member'));
			expect(view.$('.group-member:eq(1) .role-display-name').text()).toEqual(t('customgroups', 'Member'));
		});

		it('removes row when member deleted', function() {
			var member1 = collection.add({
				id: 'user1',
				userDisplayName: 'User One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			var member2 = collection.add({
				id: 'user2',
				userDisplayName: 'User Two',
				role: OCA.CustomGroups.ROLE_MEMBER
			});

			collection.remove(member1);
			expect(view.$('.group-member').length).toEqual(1);
			expect(view.$('.group-member:eq(0) .user-display-name').text()).toEqual('User Two');

			collection.remove(member2);
			expect(view.$('.group-member').length).toEqual(0);
		});
	});

	describe('adding members', function() {
		beforeEach(function() {
			view.remove();

			model = new OCA.CustomGroups.GroupModel({
				id: 'group1',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			collection = sinon.createStubInstance(OCA.CustomGroups.MembersCollection);
			model.getChildrenCollection = sinon.stub().returns(collection);

			view = new OCA.CustomGroups.MembersView(model);
			view.render();
		});

		it('does not render input field if the current user cannot admin this group', function() {
			model.set({role: OCA.CustomGroups.ROLE_MEMBER});
			view.render();
			expect(view.$('.member-input-field').length).toEqual(0);

			model.set({role: OCA.CustomGroups.ROLE_ADMIN});
			view.render();
			expect(view.$('.member-input-field').length).toEqual(1);
		});

		it('creates member into collection', function() {
			view.membersInput.trigger('select', {
				userId: 'newuser',
				displayName: 'new user display name'
			});
			expect(collection.create.calledOnce).toEqual(true);
			expect(collection.create.getCall(0).args[0]).toEqual({
				id: 'newuser',
				userDisplayName: 'new user display name'
			});

			collection.create.yieldTo('success');
			expect(view.$('.member-input-field').val()).toEqual('');
		});

		it('shows notification in case of error', function() {
			var notificationStub = sinon.stub(OC.Notification, 'showTemporary');
			view.membersInput.trigger('select', {
				userId: 'newuser'
			});

			expect(collection.create.calledOnce).toEqual(true);
			collection.create.yieldTo('error', collection, {status: 412} );

			expect(notificationStub.calledOnce).toEqual(true);

			notificationStub.restore();
		});
	});

	describe('actions', function() {
		var confirmStub;
		var currentUserModel;

		beforeEach(function() {
			view.render();
			confirmStub = sinon.stub(OC.dialogs, 'confirm');
			currentUserModel = collection.add({
				id: 'currentUser',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			currentUserModel.destroy = sinon.stub();
			collection.add({
				id: 'anotherAdmin',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			collection.add({
				id: 'anotherMember',
				role: OCA.CustomGroups.ROLE_MEMBER
			});
		});
		afterEach(function() {
			confirmStub.restore();
		});

		describe('leaving group', function() {
			it('asks for confirmation before leaving group when clicking specific button', function() {
				view.$('.action-leave-group').click();
				confirmStub.yield(true);

				expect(confirmStub.calledOnce).toEqual(true);
				expect(confirmStub.getCall(0).args[0]).toContain('leav');
				expect(currentUserModel.destroy.calledOnce).toEqual(true);
			});
			it('asks for confirmation before leaving group when deleting self from list', function() {
				view.$('.group-member:eq(0) .action-delete-member').click();
				confirmStub.yield(true);

				expect(confirmStub.calledOnce).toEqual(true);
				expect(confirmStub.getCall(0).args[0]).toContain('leav');
				expect(currentUserModel.destroy.calledOnce).toEqual(true);
			});
			it('does not delete if aborted', function() {
				view.$('.action-leave-group').click();
				confirmStub.yield(false);

				expect(currentUserModel.destroy.notCalled).toEqual(true);
			});
		});

		describe('deleting members', function() {
			var model;

			beforeEach(function() {
				model = collection.at(1);

				model.destroy = sinon.stub();
			});

			it('asks for confirmation when deleting member', function() {
				view.$('.group-member:eq(1) .action-delete-member').click();
				confirmStub.yield(true);

				expect(confirmStub.calledOnce).toEqual(true);
				expect(model.destroy.calledOnce).toEqual(true);
			});
			it('does not delete if aborted', function() {
				confirmStub.returns(false);
				view.$('.group-member:eq(1) .action-delete-member').click();

				expect(model.destroy.notCalled).toEqual(true);
			});
		});

		describe('changing roles', function() {
			var anotherAdmin;
			var anotherMember;

			beforeEach(function() {
				anotherAdmin = collection.at(1);
				anotherMember = collection.at(2);
			});

			it('switches role when clicking change role', function() {
				view.$('.group-member:eq(1) .action-change-member-role').click();
				expect(anotherAdmin.get('role')).toEqual(OCA.CustomGroups.ROLE_MEMBER);

				view.$('.group-member:eq(2) .action-change-member-role').click();
				expect(anotherMember.get('role')).toEqual(OCA.CustomGroups.ROLE_ADMIN);

				expect(confirmStub.notCalled).toEqual(true);
			});
			it('asks for confirmation before removing own admin powers', function() {
				view.$('.group-member:eq(0) .action-change-member-role').click();
				confirmStub.yield(true);
				expect(confirmStub.calledOnce).toEqual(true);

				expect(currentUserModel.get('role')).toEqual(OCA.CustomGroups.ROLE_MEMBER);
			});
			it('does not remove admin role if aborted', function() {
				view.$('.group-member:eq(0) .action-change-member-role').click();
				confirmStub.yield(false);
				expect(confirmStub.calledOnce).toEqual(true);

				expect(currentUserModel.get('role')).toEqual(OCA.CustomGroups.ROLE_ADMIN);
			});
		});
	});

	describe('loading', function() {
		it('displays spinner while fetching', function() {
			view.render();

			collection.sync = sinon.spy(collection, 'sync');

			collection.fetch();

			expect(view.$('.loading').hasClass('hidden')).toEqual(false);

			expect(collection.sync.calledOnce).toEqual(true);
			collection.sync.yieldTo('success');

			expect(view.$('.loading').hasClass('hidden')).toEqual(true);
		});
	});
});
