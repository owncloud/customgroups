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
	var NS = '{' + OC.Files.Client.NS_OWNCLOUD + '}';

	/**
	 * @class OCA.CustomGroups.CustomGroupModel
	 * @classdesc
	 *
	 */
	var MemberModel = OC.Backbone.Model.extend(
		/** @lends OCA.CustomGroups.MemberModel.prototype */ {
		sync: OC.Backbone.davSync,

		idAttribute: 'userId',

		url: function() {
			return this.collection.group.get('href') + this.get('userId');
		},

		davProperties: {
			'userId': NS + 'user-id',
			'role': NS + 'role'
		},

		parse: function(data) {
			data.role = parseInt(data.role, 10);
			return data;
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.MemberModel = MemberModel;

})(OC, OCA);

