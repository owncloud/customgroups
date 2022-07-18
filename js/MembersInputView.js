/*
 * Copyright (c) 2017
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(OC) {
	/**
	 * @class OC.SystemTags.MembersInputView
	 * @classdesc
	 *
	 * Displays a file's system tags
	 *
	 */
	var MembersInputView = OC.Backbone.View.extend(
		/** @lends OCA.CustomGroups.MembersInputView.prototype */ {

		_rendered: false,

		_newTag: null,

		className: 'member-input-view',

		/** @type {string} **/
		batchActionSeparator: ',',

		template: function(data) {
			return OCA.CustomGroups.Templates.membersInput(data);
		},

		itemTemplate: function(data) {
			return OCA.CustomGroups.Templates.membersInputItem(data);
		},

		/**
		 * Creates a new MembersInputView
		 *
		 * @param {Object} [options]
		 */
		initialize: function(options) {
			options = options || {};

			this.groupUri = options.groupUri;
		},

		/**
		 * Renders this view
		 */
		render: function() {
			this.$el.html(this.template({
				placeholderText: t('customgroups', 'Add user to this group')
			}));

			this.$field = this.$('input');
			this.$field.autocomplete({
				minLength: 1,
				delay: 750,
				focus: function(event) {
					event.preventDefault();
				},
				source: _.bind(this.autocompleteHandler, this),
				select: _.bind(this._onSelect, this)
			}).data('ui-autocomplete')._renderItem = _.bind(this.autocompleteRenderItem, this);

			this.delegateEvents();
		},

		getValue: function() {
			return this.$field.val();
		},

		setValue: function(value) {
			this.$field.val(value);
		},

		autocompleteHandler: function (search, response) {
			var self = this;
			var $loading = this.$el.find('.loading');
			var trimmedSearch = search.term.trim();
			this.$field.tooltip('hide');
			$loading.removeClass('hidden');
			$loading.addClass('inlineblock');

			if (trimmedSearch.indexOf(this.batchActionSeparator) !== -1) {
				return this._getUsersForBatchAction(trimmedSearch).then(function (res) {
					$loading.addClass('hidden');
					$loading.removeClass('inlineblock');
					if (res.found.length) {
						var labelArray = [];
						for (var i = 0; i < res.found.length; i++) {
							labelArray.push(res.found[i].displayName);
						}
						return response({
							osc: {
								batch: res.found,
								failedBatch: res.notFound,
								displayName: labelArray.join(', '),
								userId: labelArray.join(', '),
								typeInfo: t('core', 'Add multiple users and guests')
							}
						});
					}

					self._displayError(t('core', 'No users found'));
					return response();
				})
			}

			$.ajax({
				url: OC.generateUrl('/apps/customgroups/members'),
				contentType: 'application/json',
				dataType: 'json',
				data: {
					group: this.groupUri,
					pattern: trimmedSearch,
					limit: 200
				}
			}).done(function (result, type, xhr) {
					$loading.addClass('hidden');
					$loading.removeClass('inlineblock');

					if (xhr.status !== 200) {
						if (result.message) {
							OC.Notification.showTemporary(result.message);
						} else {
							OC.Notification.showTemporary(t('customgroups', 'An error occurred while searching users'));
						}
						response();
						return;
					}

					var entries = result.results;
					if (entries.length > 0) {
						entries.sort(function (a, b) {
							return OC.Util.naturalSortCompare(a.displayName, b.displayName);
						});
						self.$field.removeClass('error')
							.tooltip('hide')
							.autocomplete("option", "autoFocus", true);
						response(entries);
					} else {
						var title = t('core', 'No users found for {search}', {search: self.$field.val()});
						self._displayError(title);
						response();
					}
				}
			).fail(function() {
				$loading.addClass('hidden');
				$loading.removeClass('inlineblock');
				OC.Notification.showTemporary(t('core', 'An error occurred. Please try again'));
			});
		},

		autocompleteRenderItem: function($ul, item) {
			var typeInfo;
			if (item.typeInfo) {
				typeInfo = item.typeInfo;
			} else {
				typeInfo = item.type === 'guest' ? t('customgroups', 'Guest') : t('customgroups', 'User');
			}

			var $item = $(this.itemTemplate({
				displayName: item.displayName,
				userId: item.userId,
				typeInfo: typeInfo
			}));

			/* jshint camelcase:false */
			if (OC.config.enable_avatars) {
				$item.find('.avatardiv').avatar(item.userId, 32, undefined, undefined, undefined, item.displayName);
			}
			$ul.append($item);
			return $item;
		},

		_onSelect: function(e, s) {
			e.preventDefault();
			var members = s.item.batch || [s.item];

			if (s.item.failedBatch && s.item.failedBatch.length) {
				var failedUsersStr = s.item.failedBatch.join(', ');
				OC.Notification.show(
					t('core', 'Could not add the following users: {users}', {users: failedUsersStr}),
					{type: 'error'}
				);
			}

			for (var i = 0; i < members.length; i++) {
				var member = members[i];
				this.trigger('select', {
					userId: member.userId,
					displayName: member.displayName,
					type: member.type
				});
				$(e.target).val(member.userId).blur();
			}
			$(e.target).val(s.item.userId).blur();
		},

		/**
		 * Displays an error, e.g. when the autocomplete doesn't have results.
		 *
		 * @param {string} title - title of the error
		 * @private
		 */
		_displayError: function(title) {
			this.$field.addClass('error')
				.attr('data-original-title', title)
				.tooltip('hide')
				.tooltip({
					placement: 'bottom',
					trigger: 'manual'
				})
				.tooltip('fixTitle')
				.tooltip('show');
		},

		/**
		 * Returns a promise which includes all fetched users for batch actions once resolved.
		 *
		 * @param {string} search - trimmed search term
		 * @returns {Promise}
		 * @private
		 */
		_getUsersForBatchAction: function(search) {
			var foundUsers = [];
			var notFound = [];
			var promises = [];
			var users = Array.from(new Set(search.split(this.batchActionSeparator)));

			for (var i = 0; i < users.length; i++) {
				if (!users[i]) {
					continue;
				}
				var user = users[i].trim();
				promises.push(
					$.ajax({
						url: OC.generateUrl('/apps/customgroups/members'),
						contentType: 'application/json',
						dataType: 'json',
						context: { user: user },
						data: {
							group: this.groupUri,
							pattern: user,
							limit: 200
						},
					}).done(function (result) {
						for (var j = 0; j < result.results.length; j++) {
							var userToAdd = result.results[j]
							var addUser = true;

							// only add users that match exact with the search term
							if (this.user.toLowerCase() !== userToAdd.userId.toLowerCase()
								&& this.user.toLowerCase() !== userToAdd.displayName.toLowerCase()) {
								continue;
							}

							// only add new users
							for (var k = 0; k < foundUsers.length; k++) {
								if (foundUsers[k].userId.toLowerCase() === userToAdd.userId.toLowerCase()) {
									addUser = false;
									break;
								}
							}

							if (addUser) {
								foundUsers.push(userToAdd);
							}
						}
					})
				)
			}

			return Promise.all(promises).then(function() {
				for (var i = 0; i < users.length; i++) {
					if (!users[i]) continue;
					var user = users[i].trim();
					var userAdded = false;

					for (var j = 0; j < foundUsers.length; j++) {
						var search = user.toLowerCase();
						if (search === foundUsers[j].userId.toLowerCase()
							|| search === foundUsers[j].displayName.toLowerCase()) {
							userAdded = true;
							break;
						}
					}
					if (!userAdded) {
						notFound.push(user);
					}
				}
				return {found: foundUsers, notFound: notFound};
			});
		}
	});

	OCA.CustomGroups = OCA.CustomGroups || {};
	OCA.CustomGroups.MembersInputView = MembersInputView;

})(OC);

