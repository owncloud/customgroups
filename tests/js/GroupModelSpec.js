/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('GroupModel test', function() {
	var GroupModel = OCA.CustomGroups.GroupModel;
	var GroupsCollection = OCA.CustomGroups.GroupsCollection;

	it('returns the groups endpoint url with group name', function() {
		var model = new GroupModel({id: 'group@1囧'});

		expect(model.url()).toEqual(OC.linkToRemote('dav') + '/customgroups/groups/group%401%E5%9B%A7');
	});

	it('removes itself from groups list if current user leaves the group', function() {
		var groupId = 'group@1囧';
		var userId = 'user@example.com';
		var collection = new GroupsCollection([
			{
				id: groupId,
				displayName: 'group1呵呵'
			}
		], {userId: userId});

		var subCollection = collection.at(0).getChildrenCollection();
		var memberModel = new OCA.CustomGroups.MemberModel({
			id: userId
		});
		// add manually, this would usually appear through fetching
		subCollection.add(memberModel);
	
		expect(collection.get(groupId)).toBeDefined();

		// now remove self from the member list (leave group)
		subCollection.remove(userId);

		expect(collection.get(groupId)).not.toBeDefined();
	});

});
