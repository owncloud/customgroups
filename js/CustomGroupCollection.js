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

		_userId: null,

		initialize: function(models, options) {
			options = options || {};

			this._userId = options.userId;
		},

		getUserId: function() {
			return this._userId;
		},

		url: function() {
			if (!this._userId) {
				return OC.linkToRemote('dav') + '/customgroups/groups/';
			} else {
				return OC.linkToRemote('dav') + '/customgroups/users/' + this._userId + '/';
			}
		},

		comparator: function(a, b) {
			return OC.Util.naturalSortCompare(a.get('displayName'), b.get('displayName'));
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.CustomGroupCollection = CustomGroupCollection;

})(OC, OCA);

