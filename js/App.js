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
			this.$groupsContainer = $('#customgroups .groups-container').removeClass('icon-loading');
			this.$membersContainer = $('#customgroups .members-container').removeClass('icon-loading');

			var groupsCollection = new OCA.CustomGroups.CustomGroupCollection([], {
				// admins can see all groups so don't set a user filter
				userId: (OC.isUserAdmin() ? null : OC.getCurrentUser().uid)
			});

			var view = new OCA.CustomGroups.CustomGroupsView(groupsCollection);
			view.render();

			// TODO: empty view in case no group is selected

			groupsCollection.fetch();

			this.$groupsContainer.append(view.$el);

			view.on('select', this._onSelectGroup, this);

			OC.Util.History.addOnPopStateHandler(_.bind(this._onPopState, this));
		},

		_onPopState: function(state) {
			var groupId = state.group;
			if (!groupId) {
				this._onSelectGroup(null);
			} else {
				// TODO: need to wait for list to load before switching
				this._onSelectGroup(groupId);
			}
		},

		_onSelectGroup: function(group) {
			this.$membersContainer.empty();
			var state = {};
			if (group !== null) {
				var membersView = new OCA.CustomGroups.MembersView(group);
				this.$membersContainer.append(membersView.$el);
				state.group = group.id;
			} else {
				// TODO: render page with hint about selecting a group
			}

			OC.Util.History.pushState(state);
		}
	};

	OCA.CustomGroups = _.extend({
		ROLE_MEMBER: 'member',
		ROLE_ADMIN: 'admin'
	}, OCA.CustomGroups);

	OCA.CustomGroups.App = App;

})(OCA);

$(document).ready(function() {
	OCA.CustomGroups.App.initialize();
});
