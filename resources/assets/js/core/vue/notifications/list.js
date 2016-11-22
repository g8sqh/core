/**
 * The notification list.
 *
 * Displays all InAppNotifications of the user in a list.
 */
biigle.$viewModel('notifications-list', function (element) {
    var notification = {
        props: ['item', 'removeItem'],
        data: function () {
            return {
                isLoading: false
            };
        },
        computed: {
            classObject: function () {
                if (this.item.data.type) {
                    return 'panel-' + this.item.data.type;
                }

                return 'panel-default';
            },
            isUnread: function () {
                return this.item.read_at === null;
            }
        },
        methods: {
            markRead: function (ignoreError) {
                var _this = this;
                this.isLoading = true;
                biigle.api.notifications.markRead({id: this.item.id}, {})
                    .then(function (response) {
                        _this.item.read_at = new Date();
                        if (_this.removeItem) {
                            biigle.notifications.store.remove(_this.item.id);
                        }
                    }, function (response) {
                        if (!ignoreError) {
                            biigle.messages.store.handleErrorResponse(response);
                        }
                    })
                    .finally(function () {
                        _this.isLoading = false;
                    });

            }
        }
    };

    new Vue({
        el: element,
        components: {
            notification: notification
        },
        data: {
            notifications: biigle.notifications.store.all
        },
        methods: {
            hasNotifications: function () {
                return biigle.notifications.store.count() > 0;
            }
        }
    });
});