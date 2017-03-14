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
	 * @class OCA.CustomGroups.ShareeCollection
	 * @classdesc
	 *
	 */
	var ShareeCollection = OC.Backbone.Collection.extend(
		/** @lends OCA.CustomGroups.ShareeCollection.prototype */ {

		_userId: null,

		initialize: function(models, options) {
			options = options || {};

			this._userId = options.userId;
		},

		getUserId: function() {
			return this._userId;
		},

		url: function() {
			var patternPart = '';
			if (this.pattern) {
				patternPart = '&pattern=' + encodeURIComponent(this.pattern);
			}
			return OC.linkToOCS('apps/files_sharing/api/v1') + 'sharees?format=json&itemType=file&shareType=0' + patternPart;
		},

		parse: function(data) {
			return _.map(data.ocs.data.users, function(entry) {
				return {
					text: entry.label,
					id: entry.value.shareWith
				};
			});
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.ShareeCollection = ShareeCollection;

})(OC, OCA);

