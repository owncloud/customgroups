/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('MembersInputView test', function() {
	var view;
	var avatarStub;

	beforeEach(function() {
		avatarStub = sinon.stub($.fn, 'avatar');

		view = new OCA.CustomGroups.MembersInputView({groupUri: 'group1'});
	});
	afterEach(function() {
		view.remove();
		view = null;

		avatarStub.restore();
	});

	it('renders input field', function() {
		view.render();
		expect(view.$('input.member-input-field').length).toEqual(1);
	});

	describe('autocomplete', function() {
		var autocompleteStub;
		var autocompleteOptions;

		beforeEach(function() {
			autocompleteStub = sinon.stub($.fn, 'autocomplete').callsFake(function() {
				$(this).data('ui-autocomplete', {});
				return $(this);
			});
			view.render();

			autocompleteOptions = autocompleteStub.getCall(0).args[0];
		});
		afterEach(function() { 
			autocompleteStub.restore(); 
		});

		it('searches by pattern', function() {
			var responseCallback = sinon.stub();
			autocompleteOptions.source({
				term: 'search term'
			}, responseCallback);

			expect(fakeServer.requests.length).toEqual(1);
			var request = fakeServer.requests[0];

			expect(request.url).toEqual(
				OC.generateUrl('/apps/customgroups/members' +
				'?group=group1&pattern=search+term&limit=200')
			);
			expect(request.method).toEqual('GET');

			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify({
					results: [
						{userId: 'user2', displayName: 'User Two'},
						{userId: 'user1', displayName: 'User One'}
					]
				})
			);

			expect(responseCallback.calledOnce).toEqual(true);
			expect(responseCallback.getCall(0).args[0]).toEqual([
				{userId: 'user1', displayName: 'User One'},
				{userId: 'user2', displayName: 'User Two'}
			]);
		});

		it('renders results', function() {
			var renderItemFunc = view.$('.member-input-field').data('ui-autocomplete')._renderItem;
			var $ul = $('<ul>');
			var $li = renderItemFunc($ul, {
				userId: 'user1',
				displayName: 'User One'
			});

			expect($li.is('li')).toEqual(true);
			expect(avatarStub.calledOnce).toEqual(true);
			expect($ul.find('.autocomplete-item-text').text()).toEqual('User One');
			expect($li.find('.autocomplete-item-text').text()).toEqual('User One');
		});

		it('renders tooltip error if no results', function() {
			var responseCallback = sinon.stub();
			autocompleteOptions.source({
				term: 'search term'
			}, responseCallback);

			expect(fakeServer.requests.length).toEqual(1);
			var request = fakeServer.requests[0];
			request.respond(
				200,
				{ 'Content-Type': 'application/json' },
				JSON.stringify({
					results: []
				})
			);

			expect(responseCallback.calledOnce).toEqual(true);
			expect(responseCallback.getCall(0).args[0]).not.toBeDefined();

			expect(view.$('.member-input-field').attr('data-original-title')).toContain('No users found');
		});

		it('triggers select event on select', function() {
			var handler = sinon.stub();
			view.on('select', handler);

			autocompleteOptions.select(new $.Event('select'), {
				item: {userId: 'user2', displayName: 'User Two'}
			});

			expect(handler.calledOnce).toEqual(true);
			expect(handler.getCall(0).args[0]).toEqual({
				userId: 'user2',
				displayName: 'User Two'
			});
		});
		

	});
});
