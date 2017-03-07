/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('GroupsCollection test', function() {
	var GroupsCollection = OCA.CustomGroups.GroupsCollection;

	describe('url', function() {
		it('returns the groups endpoint url if no user id was given (superadmin mode)', function() {
			var collection = new GroupsCollection();

			expect(collection.url()).toEqual(OC.linkToRemote('dav') + '/customgroups/groups/');
		});
		it('returns the users endpoint url if a user id was given', function() {
			var collection = new GroupsCollection([], {userId: 'someuser0@_.@-'});

			expect(collection.url()).toEqual(OC.linkToRemote('dav') + '/customgroups/users/someuser0%40_.%40-/');
		});
	});

});
