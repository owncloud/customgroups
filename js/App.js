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

	var App = OC.Backbone.View.extend({
		events: {
			'click .sidebar .action-close': '_onClickClose'
		},

		initialize: function() {
			this.collection = new OCA.CustomGroups.GroupsCollection([], {
				// admins can see all groups so don't set a user filter
				userId: (OC.isUserAdmin() ? null : OC.getCurrentUser().uid)
			});
			this.listView = new OCA.CustomGroups.GroupsView(this.collection);
			this.listView.on('select', this._onSelectGroup, this);

			this.render();

			this.collection.fetchNext();
		},

		template: function(data) {
			return OCA.CustomGroups.Templates.app(data);
		},

		render: function() {
			this.$el.html(this.template({
				customGroupsTitle: t('customgroups', 'Custom Groups')
			}));

			this.$groupsContainer = this.$('.groups-container').removeClass('icon-loading');
			this.$membersContainer = this.$('.members-container').removeClass('icon-loading');

			this.listView.render();
			this.$groupsContainer.append(this.listView.$el);
		},

		_onSelectGroup: function(group) {
			this.$membersContainer.empty();
			if (this.membersView) {
				this.membersView.remove();
			}

			if (group !== null) {
				OC.Apps.showAppSidebar(this.$membersContainer);
				this.membersView = new OCA.CustomGroups.MembersView(group);
				this.$membersContainer.append(this.membersView.$el);

				this.membersView.render();
			} else {
				OC.Apps.hideAppSidebar(this.$membersContainer);
			}
		},

		_onClickClose: function(ev) {
			ev.preventDefault();
			OC.Apps.hideAppSidebar(this.$membersContainer);
			return false;
		},

	});

	OCA.CustomGroups = _.extend({
		ROLE_MEMBER: 'member',
		ROLE_ADMIN: 'admin'
	}, OCA.CustomGroups);

	OCA.CustomGroups.App = App;

})(OCA);

$(document).ready(function() {
	var app = new OCA.CustomGroups.App();
	$('#customgroups').append(app.$el);
});
