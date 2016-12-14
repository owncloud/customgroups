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
	var NS = '{' + OC.Files.Client.NS_OWNCLOUD + '}';

	/**
	 * @class OCA.CustomGroups.CustomGroupModel
	 * @classdesc
	 *
	 */
	var CustomGroupModel = OC.Backbone.Model.extend(
		/** @lends OCA.CustomGroups.CustomGroupModel.prototype */ {
		sync: OC.Backbone.davSync,

		url: function() {
			return OC.linkToRemote('dav') + '/customgroups/groups/' + this.id;
		},

		/**
		 * Returns the members collection for this group
		 *
		 * @return {OC.Backbone.Collection} collection
		 */
		getInnerCollection: function() {
			// TODO proper class + cache
			return new OC.Backbone.Collection({
				uri: this.id
			});
		},

		davProperties: {
			'displayName': NS + 'display-name'
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.CustomGroupModel = CustomGroupModel;

})(OC, OCA);

