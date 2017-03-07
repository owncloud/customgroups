/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('MembersCollection test', function() {
	var GroupModel = OCA.CustomGroups.GroupModel;

	it('returns the groups endpoint url with group name', function() {
		var groupModel = new GroupModel({id: 'group@1囧'});

		var subCollection = groupModel.getChildrenCollection();

		expect(subCollection.url())
			.toEqual(OC.linkToRemote('dav') + '/customgroups/groups/group%401%E5%9B%A7/');
	});

});
