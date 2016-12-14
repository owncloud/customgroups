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
	 * @class OCA.CustomGroups.CustomGroupCollection
	 * @classdesc
	 *
	 */
	var CustomGroupCollection = OC.Backbone.Collection.extend(
		/** @lends OCA.CustomGroups.CustomGroupCollection.prototype */ {

		sync: OC.Backbone.davSync,
		model: OCA.CustomGroups.CustomGroupModel,

		url: function() {
			return OC.linkToRemote('dav') + '/customgroups/groups/';
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.CustomGroupCollection = CustomGroupCollection;

})(OC, OCA);

