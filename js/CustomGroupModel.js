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

		hasInnerCollection: true,

		url: function() {
			return this.get('href');
		},

		_innerCollection: null,

		/**
		 * Returns the members collection for this group
		 *
		 * @return {OC.Backbone.Collection} collection
		 */
		getMembersCollection: function() {
			var self = this;
			if (!this._innerCollection) {
				this._innerCollection = new OCA.CustomGroups.MembersCollection([], {
					group: this
				});
				// group was part of a CustomGroupCollection ?
				if (this.collection) {
					// get owner
					var userId = this.collection.getUserId();
					if (userId) {
						// detect removal of list owner
						this._innerCollection.on('remove', function(model) {
							if (model.get('userId') === userId) {
								// remove current group from the list
								self.collection.remove(self);
							}
						});
					}
				}
			}
			return this._innerCollection;
		},

		davProperties: {
			'id': NS + 'group-id',
			'displayName': NS + 'display-name',
			'role': NS + 'role'
		},

		parse: function(data) {
			data.role = parseInt(data.role, 10);
			return data;
		}
	});

	OCA.CustomGroups = _.extend({}, OCA.CustomGroups);
	OCA.CustomGroups.CustomGroupModel = CustomGroupModel;

})(OC, OCA);

