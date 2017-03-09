/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('GroupsView test', function() {
	var view;
	var collection;

	var imageplaceholderStub;

	beforeEach(function() {
		imageplaceholderStub = sinon.stub($.fn, 'imageplaceholder');

		collection = new OCA.CustomGroups.GroupsCollection();
		view = new OCA.CustomGroups.GroupsView(collection);
	});
	afterEach(function() {
		view.remove();
		view = null;
		collection = null;

		imageplaceholderStub.restore();
	});

	describe('rendering', function() {
		beforeEach(function() {
			view.render();
		});
		
		it('renders empty list at first', function() {
			expect(view.$('.group-list').length).toBeDefined();
		});

		it('renders groups as they are added', function() {
			collection.add([{
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			}, { 
				id: 'group2',
				displayName: 'Group Two',
				role: OCA.CustomGroups.ROLE_MEMBER
			}]);

			expect(view.$('.group').length).toEqual(2);
			expect(view.$('.group:eq(0) .group-display-name').text()).toEqual('Group One');
			expect(view.$('.group:eq(0) .role-display-name').text()).toEqual(t('customgroups', 'Group admin'));
			expect(view.$('.group:eq(1) .group-display-name').text()).toEqual('Group Two');
			expect(view.$('.group:eq(1) .role-display-name').text()).toEqual(t('customgroups', 'Member'));

			// avatar
			expect(imageplaceholderStub.calledTwice).toEqual(true);
			expect(imageplaceholderStub.getCall(0).thisValue.get(0)).toEqual(view.$('.group:eq(0) .avatar').get(0));
			expect(imageplaceholderStub.getCall(0).args[0]).toEqual('group1:Group One');
			expect(imageplaceholderStub.getCall(0).args[1]).toEqual('Group One');
			expect(imageplaceholderStub.getCall(1).thisValue.get(0)).toEqual(view.$('.group:eq(1) .avatar').get(0));
			expect(imageplaceholderStub.getCall(1).args[0]).toEqual('group2:Group Two');
			expect(imageplaceholderStub.getCall(1).args[1]).toEqual('Group Two');
		});

		it('renders admin actions when role is group admin', function() {
			collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			expect(view.$('.group:eq(0) .action-rename-group').length).toEqual(1);
			expect(view.$('.group:eq(0) .action-delete-group').length).toEqual(1);
		});
		it('does not renders admin actions when role is member', function() {
			collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_MEMBER
			});

			expect(view.$('.group:eq(0) .action-rename-group').length).toEqual(0);
			expect(view.$('.group:eq(0) .action-delete-group').length).toEqual(0);
		});

		it('rerenders row when group changed', function() {
			var group1 = collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			collection.add({
				id: 'group2',
				displayName: 'Group Two',
				role: OCA.CustomGroups.ROLE_MEMBER
			});

			group1.set({
				displayName: 'Group Renamed'
			});

			expect(view.$('.group').length).toEqual(2);
			expect(view.$('.group:eq(0) .group-display-name').text()).toEqual('Group Renamed');
			expect(view.$('.group:eq(1) .group-display-name').text()).toEqual('Group Two');
		});

		it('removes row when group deleted', function() {
			var group1 = collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			var group2 = collection.add({
				id: 'group2',
				displayName: 'Group Two',
				role: OCA.CustomGroups.ROLE_MEMBER
			});


			collection.remove(group1);
			expect(view.$('.group').length).toEqual(1);
			expect(view.$('.group:eq(0) .group-display-name').text()).toEqual('Group Two');

			collection.remove(group2);
			expect(view.$('.group').length).toEqual(0);
		});

		it('updates hidden message when needed', function() {
			var group1 = collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			expect(view.$('.empty').hasClass('hidden')).toEqual(true);
			expect(view.$('.grid').hasClass('hidden')).toEqual(false);

			collection.remove(group1);

			expect(view.$('.empty').hasClass('hidden')).toEqual(false);
			expect(view.$('.grid').hasClass('hidden')).toEqual(true);
		});
	});

	describe('selection', function() {
		beforeEach(function() {
			view.render();
			collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
			collection.add({
				id: 'group2',
				displayName: 'Group Two',
				role: OCA.CustomGroups.ROLE_MEMBER
			});
		});

		it('updates "active" class when group is selected', function() {
			var $row1 = view.$('.group:eq(0)');
			var $row2 = view.$('.group:eq(1)');

			expect($row1.hasClass('active')).toEqual(false);
			expect($row2.hasClass('active')).toEqual(false);

			$row1.click();
			expect($row1.hasClass('active')).toEqual(true);
			expect($row2.hasClass('active')).toEqual(false);

			$row2.click();
			expect($row1.hasClass('active')).toEqual(false);
			expect($row2.hasClass('active')).toEqual(true);
		});

		it('triggers "select" event when group is clicked', function() {
			var handler = sinon.stub();
			view.on('select', handler);

			view.$('.group:eq(1)').click();

			expect(handler.calledOnce).toEqual(true);
			expect(handler.calledWith(collection.at(1))).toEqual(true);
		});

		it('triggers "select" event with null when selected group is deleted', function() {
			view.$('.group:eq(1)').click();

			var handler = sinon.stub();
			view.on('select', handler);

			collection.remove('group2');

			expect(handler.calledOnce).toEqual(true);
			expect(handler.calledWith(null)).toEqual(true);
		});
	});

	describe('creating groups', function() {
		beforeEach(function() {
			view.remove();
			collection = sinon.createStubInstance(OCA.CustomGroups.GroupsCollection);
			view = new OCA.CustomGroups.GroupsView(collection);
			view.render();
		});

		it('creates group into collection and selects it', function() {
			view.select = sinon.stub();

			view.$('[name=groupName]').val('newgroup');
			view.$('[name=customGroupsCreationForm]').submit();

			expect(collection.create.calledOnce).toEqual(true);
			expect(collection.create.getCall(0).args[0]).toEqual({
				id: 'newgroup',
				displayName: 'newgroup',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			var model = new OCA.CustomGroups.GroupModel({id: 'newgroup'});
			collection.create.yieldTo('success', model);

			expect(view.$('[name=groupName]').val()).toEqual('');
			expect(view.select.calledOnce).toEqual(true);
			expect(view.select.calledWith(model)).toEqual(true);
		});

		it('attempts multiple uris in case of name conflict', function() {
			view.$('[name=groupName]').val('newgroup');
			view.$('[name=customGroupsCreationForm]').submit();

			expect(collection.create.calledOnce).toEqual(true);
			expect(collection.create.getCall(0).args[0]).toEqual({
				id: 'newgroup',
				displayName: 'newgroup',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			collection.create.yieldTo('error', collection, {status: 405} );

			expect(collection.create.calledTwice).toEqual(true);
			expect(collection.create.getCall(1).args[0]).toEqual({
				id: 'newgroup2',
				displayName: 'newgroup',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
		});

		it('shows notification in case of error', function() {
			var notificationStub = sinon.stub(OC.Notification, 'showTemporary');
			view.$('[name=groupName]').val('newgroup');
			view.$('[name=customGroupsCreationForm]').submit();

			expect(collection.create.calledOnce).toEqual(true);
			expect(collection.create.getCall(0).args[0]).toEqual({
				id: 'newgroup',
				displayName: 'newgroup',
				role: OCA.CustomGroups.ROLE_ADMIN
			});

			collection.create.yieldTo('error', collection, {status: 404} );

			expect(notificationStub.calledOnce).toEqual(true);

			notificationStub.restore();
		});
	});

	describe('actions', function() {
		var promptStub;
		var confirmStub;
		var model;

		beforeEach(function() {
			promptStub = sinon.stub(window, 'prompt');
			confirmStub = sinon.stub(OC.dialogs, 'confirm');

			view.render();
			model = collection.add({
				id: 'group1',
				displayName: 'Group One',
				role: OCA.CustomGroups.ROLE_ADMIN
			});
		});
		afterEach(function() { 
			promptStub.restore();
			confirmStub.restore();
		});

		describe('rename group', function() {
			beforeEach(function() {
				model.save = sinon.stub();
			});

			it('saves model with new name on confirm', function() {
				promptStub.returns('Group Renamed');
				view.$('.group:eq(0) .action-rename-group').click();
				expect(model.save.calledOnce).toEqual(true);
				expect(model.save.getCall(0).args[0]).toEqual({
					displayName: 'Group Renamed'
				});

			});
			it('does not save model on abort', function() {
				promptStub.returns(null);
				view.$('.group:eq(0) .action-rename-group').click();
				expect(model.save.notCalled).toEqual(true);
			});
		});
		describe('delete group', function() {
			beforeEach(function() {
				model.destroy = sinon.stub();
			});

			it('destroy model with on confirm', function() {
				view.$('.group:eq(0) .action-delete-group').click();
				confirmStub.yield(true);
				expect(model.destroy.calledOnce).toEqual(true);
			});
			it('does not destroy model on abort', function() {
				view.$('.group:eq(0) .action-delete-group').click();
				confirmStub.yield(false);
				expect(model.destroy.notCalled).toEqual(true);
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
