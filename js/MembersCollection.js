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
	var MembersCollection = OC.Backbone.Collection.extend(
		/** @lends OCA.CustomGroups.MembersCollection.prototype */ {

		sync: OC.Backbone.davSync,
		model: OCA.CustomGroups.MemberModel,

		usePUT: true,

		initialize: function(models, options) {
			options = options || {};

			if (!options.group) {
				throw 'Missing "group" option';
			}
			
			// set empty model
			this.group = options.group;
		},

		url: function() {
			return this.group.get('href');
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.MembersCollection = MembersCollection;

})(OC, OCA);

