/*
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OCA) {

	var App = {
		initialize: function() {
			$('#app-navigation').removeClass('icon-loading');
			$('#app-content .container').removeClass('icon-loading');

			var groupsCollection = new OCA.CustomGroups.CustomGroupCollection([], {
				// admins can see all groups so don't set a user filter
				userId: (OC.isUserAdmin() ? null : OC.getCurrentUser().uid)
			});

			var view = new OCA.CustomGroups.CustomGroupsView(groupsCollection);
			view.render();

			// TODO: empty view in case no group is selected

			groupsCollection.fetch();

			$('#app-navigation').append(view.$el);

			view.on('select', this._onSelectGroup, this);

			this._onSelectGroup(null);
		},

		_onSelectGroup: function(group) {
			var $container = $('#app-content .container').empty(); 
			if (group !== null) {
				var membersView = new OCA.CustomGroups.MembersView(group);
				$container.append(membersView.$el);
			} else {
				// TODO: render page with hint about selecting a group
			}
		}
	};

	OCA.CustomGroups = _.extend({
		ROLE_MEMBER: 0,
		ROLE_ADMIN: 1
	}, OCA.CustomGroups);

	OCA.CustomGroups.App = App;

})(OCA);

$(document).ready(function() {
	OCA.CustomGroups.App.initialize();
});
