/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('App test', function() {
	var app;

	afterEach(function() {
		if (app) {
			app.remove();
		}
		app = null;
	});

	describe('rendering', function() {
		it('renders containers', function() {
			app = new OCA.CustomGroups.App();
			expect(app.$('.groups-container').length).toEqual(1);
			expect(app.$('.members-container').length).toEqual(1);
		});
	});

	describe('group collection', function() {
		var isUserAdminStub;
		var getCurrentUserStub;
		var collectionClassStub;
		var collectionStub;

		beforeEach(function() {
			isUserAdminStub = sinon.stub(OC, 'isUserAdmin');
			getCurrentUserStub = sinon.stub(OC, 'getCurrentUser');
			getCurrentUserStub.returns({uid: 'someadmin'});

			collectionStub = sinon.createStubInstance(OCA.CustomGroups.GroupsCollection);

			collectionClassStub = sinon.stub(OCA.CustomGroups, 'GroupsCollection');
			collectionClassStub.returns(collectionStub);
		});
		afterEach(function() {
			isUserAdminStub.restore();
			getCurrentUserStub.restore();
			collectionClassStub.restore();
		});

		it('initializes collection with null user for superadmin', function() {
			isUserAdminStub.returns(true);

			app = new OCA.CustomGroups.App();
			expect(collectionClassStub.calledOnce).toEqual(true);
			expect(collectionClassStub.getCall(0).args[1]).toEqual({
				userId: null
			});
		});
		it('initializes collection with uid for regular users', function() {
			isUserAdminStub.returns(false);
			app = new OCA.CustomGroups.App();
			expect(collectionClassStub.calledOnce).toEqual(true);
			expect(collectionClassStub.getCall(0).args[1]).toEqual({
				userId: 'someadmin'
			});
		});
		it('fetches collection', function() {
			app = new OCA.CustomGroups.App();

			expect(collectionStub.fetch.calledOnce).toEqual(true);
		});
	});
	describe('selection', function() {
		var collectionClassStub;
		var collection;

		var membersViewClassStub;
		var membersViewStub;

		beforeEach(function() {
			app = new OCA.CustomGroups.App();

			collection = new OCA.CustomGroups.GroupsCollection([
				{
					id: 'group1',
					displayName: 'group one'
				},
				{
					id: 'group2',
					displayName: 'group two'
				}
			]);

			collectionClassStub = sinon.stub(OCA.CustomGroups, 'GroupsCollection');
			collectionClassStub.returns(collection);

			membersViewStub = sinon.createStubInstance(OCA.CustomGroups.MembersView);
			membersViewClassStub = sinon.stub(OCA.CustomGroups, 'MembersView');
			membersViewClassStub.returns(membersViewStub);
		});
		afterEach(function() {
			collectionClassStub.restore();
			membersViewClassStub.restore();
			collection = null;
		});

		it('displays sidebar', function() {
			var showSidebarStub = sinon.stub(OC.Apps, 'showAppSidebar');
			app.listView.trigger('select', collection.at(0));
			expect(showSidebarStub.calledOnce).toEqual(true);
			expect(showSidebarStub.calledWith(app.$membersContainer)).toEqual(true);
			showSidebarStub.restore();
		});

		it('updates members view when selecting a group', function() {
			app.listView.trigger('select', collection.at(0));

			expect(membersViewClassStub.getCall(0).args[0]).toEqual(collection.at(0));
			expect(membersViewStub.render.calledOnce).toEqual(true);
		});

		it('hides sidebar if group deselected', function() {
			var showSidebarStub = sinon.stub(OC.Apps, 'showAppSidebar');
			var hideSidebarStub = sinon.stub(OC.Apps, 'hideAppSidebar');
			app.listView.select(null);
			expect(showSidebarStub.notCalled).toEqual(true);
			expect(hideSidebarStub.calledOnce).toEqual(true);
			expect(hideSidebarStub.calledWith(app.$membersContainer)).toEqual(true);
			showSidebarStub.restore();
			hideSidebarStub.restore();
		});

		it('selects null if sidebar was manually closed', function() {
			var hideSidebarStub = sinon.stub(OC.Apps, 'hideAppSidebar');
			app.listView.trigger('select', collection.at(0));

			var handler = sinon.stub();
			app.listView.on('select', handler);

			// trigger close event handler that was registered
			// on the membersView
			expect(app.membersView.on.calledWith('close')).toEqual(true);
			app.membersView.on.getCall(0).args[1].call(app.membersView.on.getCall(0).args[2]);

			expect(handler.calledOnce).toEqual(true);
			expect(handler.calledWith(null)).toEqual(true);
			
			hideSidebarStub.restore();
		});

		it('destroys last members view if deselected', function() {
			app.listView.trigger('select', collection.at(0));
			app.listView.trigger('select', null);

			expect(membersViewStub.remove.calledOnce).toEqual(true);
		});
		it('destroys last member view if switching selection', function() {
			app.listView.trigger('select', collection.at(0));
			app.listView.trigger('select', collection.at(1));

			expect(membersViewStub.remove.calledOnce).toEqual(true);
		});
	});
});
