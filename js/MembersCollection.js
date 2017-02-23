/*
 * Copyright (c) 2017 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */
(function(OC, OCA) {
	/**
	 * @class OCA.CustomGroups.CustomGroupCollection
	 * @classdesc
	 *
	 */
	var MembersCollection = OC.Backbone.WebdavChildrenCollection.extend(
		/** @lends OCA.CustomGroups.MembersCollection.prototype */ {
		model: OCA.CustomGroups.MemberModel,

		url: function() {
			return OC.linkToRemote('dav') + '/customgroups/groups/' + this.collectionNode.get('id') + '/';
		},

		comparator: function(a, b) {
			var roleA = a.get('role');
			var roleB = b.get('role');
			if (roleA === roleB) {
				return OC.Util.naturalSortCompare(a.get('id'), b.get('id'));
			} else {
				// sort by "admin" first, "member" next
				if (roleA === OCA.CustomGroups.ROLE_ADMIN) {
					return -1;
				}
				return 1;
			}
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.MembersCollection = MembersCollection;

})(OC, OCA);

