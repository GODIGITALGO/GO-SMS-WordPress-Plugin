jQuery(function ($) {
    "use strict";


    var loader = jQuery(".wpnotif_load_overlay");
    var newsletter_settings = jQuery(".wpnotif-newsletter_settings");
    var mobile_field = jQuery(".wpnotif-newsletter_mobile_number-key");
    jQuery("select[name='mobile_field_type']").on('change', function () {
        var value = jQuery(this).val();
        if (value == 1) {
            mobile_field.removeAttr('required').hide();
        } else {
            mobile_field.attr('required', 'required').show();
        }
    });

    jQuery(document).on('change', ".wpnotif-newsletter_name input", function (e) {
        var inputs = jQuery(".wpnotif-newsletter_name input:checked");

        var delete_button = jQuery(".newsletter-bulk_delete");

        if (inputs.length > 0) {
            delete_button.removeClass('newsletter-hide_icon');
        } else {
            delete_button.addClass('newsletter-hide_icon');
        }
    });


    jQuery(document).on('click', '.newsletter-bulk_delete', function (e) {
        showLoader();
        var inputs = jQuery(".wpnotif-newsletter_name input:checked");
        var nids = [];
        jQuery.each(inputs, function () {
            var row = jQuery(this).closest('tr');
            nids.push(row.attr('id'));
        });
        delete_newsletter(nids);
    });

    jQuery(document).on('click', '.wpnotif-hide_modal', function (e) {
        hideWPNotifMessage();
        jQuery(this).closest('.wpnotif-modal').fadeOut('fast');
        newsletter_settings.removeClass('wpnotif_bg_blur');
    });

    jQuery(document).on('click', '.open_modal', function (e) {
        edit_id = 0;
        var modal_class = jQuery(this).data('show');
        show_modal(modal_class, jQuery(this));
    })

    function show_modal(modal_class, trigger) {
        var modal = jQuery("." + modal_class);
        if (modal.data('reset')) {
            modal.find('input, textarea, select').not('.wpnotif-fixvalue').val('').trigger('change');
            modal.find('select option:selected').prop("selected", false).trigger('change');
            modal.find('select').prop('selectedIndex', 0).trigger('change');
            modal.removeAttr('data-reset');

            modal.find('input[type="checkbox"]').prop('checked', false);

        }
        modal.find('.modal-body_child').addClass('wpnotif-hide');
        modal.find('.modal-body_primarychild').removeClass('wpnotif-hide');
        if (trigger) {
            modal.find('.modal-title').text(trigger.data('title'));
            modal.find('.modal-title-desc').text(trigger.data('title-desc'));
        }
        modal.fadeIn('fast');
        newsletter_settings.addClass('wpnotif_bg_blur');
    }

    jQuery(document).on('change update', '.wpnotif-checkbox input[type="checkbox"]', function (e) {
        var $this = jQuery(this);
        if (!$this.is(':checked')) {
            $this.closest('.wpnotif-checkbox').removeClass('selected');
        } else {
            $this.closest('.wpnotif-checkbox').addClass('selected');
        }
    });


    var edit_id = 0;
    jQuery(document).on('click', '.newsletter_edit', function (e) {
        var modal_class = jQuery(this).data('show');
        var modal = jQuery("." + modal_class);
        var data = jQuery(this).closest('.wpnotif-action').data('json');
        update_modal_data(modal, data, jQuery(this).data('title'), '');
        edit_id = jQuery(this).closest('tr').attr('id');
        modal.data('reset', '1').fadeIn('fast');
        newsletter_settings.addClass('wpnotif_bg_blur');

    });

    jQuery(document).on('click', '.newsletter_duplicate', function (e) {
        var modal_class = jQuery(this).data('show');
        var modal = jQuery("." + modal_class);
        var data = jQuery(this).closest('.wpnotif-action').data('json');
        update_modal_data(modal, data, jQuery(this).data('title'), jQuery(this).data('title-desc'));
        edit_id = 0;
        modal.removeAttr('data-reset').fadeIn('fast');
    });


    function update_modal_data(modal, data, title, title_desc) {
        modal.find('.modal-title').text(title);

        modal.find('.modal-title-desc').text(title_desc);


        jQuery.each(data, function (key, value) {
            var inp = modal.find('.wpnotif-' + key);

            if (inp.is(":checkbox")) {
                if (value == 1) {
                    inp.prop('checked', true);
                } else {
                    inp.prop('checked', false);
                }
            } else if (inp.prop('type') == 'select-multiple' || inp.prop('type') == 'select-one') {

                inp.find("option:selected").prop("selected", false);
                var data = value.split(",");
                if (data.length > 0) {
                    jQuery.each(data, function (key, value) {
                        inp.find("option[value='" + value + "']").prop("selected", true);
                    });
                }
            } else {
                inp.val(value);
            }
            inp.trigger('change');

        });
    }

    var select_all_inp = jQuery('.wpnotif_admin_conf .select_all');

    select_all_inp.on('change', function () {
        var select = jQuery(this).data('select');
        var checked = jQuery(this).is(":checked");

        if (checked) {
            jQuery(".wpnotif_select_button").removeClass('wpnotif-hide');
        } else {
            jQuery(".wpnotif_select_button").addClass('wpnotif-hide');
        }
        jQuery('.' + select).prop('checked', checked).trigger('update');
    });

    jQuery('.user-list_modify').on('click', function () {
        var checked = newsletter_settings.find('.selected_user:checked');
        if (!checked.length) {
            return;
        }
        var uids = [];

        checked.each(function () {
            jQuery(this).closest('tr').addClass('dt_row_selected wpnotif-hide');
            uids.push(jQuery(this).val());
        });


        var data = "action=wpnotif_update_user_in_list&gid=" + data_table.data('id') + "&uids=" + uids.join(",") + "&newsletter_nonce=" + nl.nonce;

        if (jQuery(this).hasClass('remove_user_from_list')) {
            data = data + "&type=remove";
        } else {
            data = data + "&type=add";
        }

        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: data,
            success: function (res) {
                if (isJSON(res)) {
                    if (res.success == 0) {
                        error_delete_sub_from_list();
                        showWPNotifErrorMessage(res.data.message);
                    } else {
                        var table = data_table.DataTable();
                        table.rows('.dt_row_selected').remove().draw();
                    }
                }
            }, error: function () {
                hideLoader();
                error_delete_sub_from_list();
                showWPNotifErrorMessage(nl.Error);
            }
        });
    })

    function error_delete_sub_from_list() {
        jQuery('.dt_row_selected').removeClass('.dt_row_selected .wpnotif-hide');
    }


    jQuery(document).on('change', '.wpnotif_admin_conf .selected_user', function (e) {

        var selected = newsletter_settings.find('.selected_user:checked').length;
        if (selected > 0) {
            jQuery(".wpnotif_select_button").removeClass('wpnotif-hide');
        } else {
            jQuery(".wpnotif_select_button").addClass('wpnotif-hide');
        }

        var select_all = false;
        if (selected === newsletter_settings.find('.selected_user').length) {
            select_all = true;
        }

        select_all_inp.prop('checked', select_all).trigger('update');
    });


    var THRESHOLD = 3200;

    function parse_data(form, data) {
        var i = 0, parse_data = [], header = [], header_defined = false;
        showLoader();

        Papa.parse(data, {
            header: false,
            dynamicTyping: false,
            worker: false,
            skipEmptyLines: true,
            step: function (results, parser) {
                var data = results.data;


                if (!header_defined) {
                    header = data;
                    header_defined = true;
                } else if (i === 0) {
                    parse_data.push(header);
                }


                parse_data.push(data);
                i++;

                parse_size++;

                if (i === THRESHOLD) {
                    parser.pause();
                    import_list(form, parse_data, parser);
                    i = 0;
                    parse_data = [];
                }

            }, complete: function () {
                import_list(form, parse_data, undefined);
            }
        });
    }

    var invalid = 0;
    var imported = 0;
    var parse_size = 0;
    var duplicate = 0;

    function import_list(form, parse_data, parser) {
        hideWPNotifMessage();

        var data = form.find('input[type="hidden"]').serialize();
        data = data + "&data=" + encodeURIComponent(JSON.stringify(parse_data));

        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: data,
            success: function (res) {
                var success = res.success;

                if (success === false) {
                    hideLoader();
                    showWPNotifErrorMessage(res.data.message);
                    return;
                }
                if (parser === undefined) {

                    if (res.data.imported) {
                        imported += res.data.imported;
                    }
                    if (res.data.invalid) {
                        invalid += res.data.invalid;
                    }

                    if (imported == 0) {
                        hideLoader();
                        showWPNotifErrorMessage(nl.duplicate_import_failed);
                        return;
                    }
                    parse_size = parse_size - 1;

                    if (imported < parse_size) {
                        var partial_msg = nl.partial_import;
                        duplicate = parse_size - imported - invalid;

                        partial_msg = partial_msg.replace("{duplicate}", duplicate);
                        partial_msg = partial_msg.replace("{invalid}", invalid);
                        showWPNotifSuccessMessage(partial_msg);
                    }

                    location.href = res.data.redirect;

                    return;
                }

                if (parser !== undefined) {
                    if (success === false) {
                        parser.abort();
                    } else {
                        parser.resume();
                    }
                }
            }, error: function () {
                showWPNotifNoticeMessage(nl.error_retrying);
                setTimeout(function () {
                    import_list(form, data, parser);
                }, 3000);
            }
        });
    }


    jQuery(document).on('click', '.wpnotif-import_data', function () {
        var form = jQuery(this).closest('form');
        var data = form.find('textarea[name="data"]').val();
        if (!data.length) {
            return false;
        }

        parse_data(form, data);

        return false;
    });


    jQuery(document).on('change', '.wpnotif_import_input_file', function () {
        var form = jQuery(this).closest('form');
        if (this.files.length) {
            var file = this.files[0];
            parse_data(form, file);
        }
    });

    jQuery(document).on('click', '.wpnotif-modal_body .wpnotif-button', function (e) {
        var $this = jQuery(this);
        var type = $this.data('type');
        var form = $this.closest('form');
        form.find('.data_type').val(type);

        if (type == 'upload_file') {
            $this.parent().find('input[type="file"]').trigger('click');
        } else if ($this.data('show')) {
            var show = $this.data('show');

            var modal_header = jQuery(this).closest('.wpnotif-modal_box').find('.wpnotif-modal_header');
            modal_header.find('.modal-title').text($this.data('title'));
            modal_header.find('.modal-title-desc').text($this.data('title-desc'));


            $this.closest('.modal-body_child').addClass('wpnotif-hide');
            jQuery("." + show).removeClass('wpnotif-hide').find('input, textarea').attr('required', 'required');
        } else if (jQuery(this).hasClass('wpnotif_submit')) {
            form.submit();
        }
    });


    var wpnotif_newsletter_list = jQuery(".wpnotif_admin_conf .wpnotif_newsletter_list");

    function refresh_newsletter() {
        var data = "action=wpnotif_refresh_newsletter&newsletter_nonce=" + nl.nonce;
        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: data,
            success: function (res) {
                if (isJSON(res)) {
                    jQuery(".wpnotif_newsletter_list").html(res.data.html);
                    update_newsletter_time();
                }
            }, error: function () {
                update_newsletter_time();
            }
        });

    }

    function update_newsletter_time() {
        setTimeout(function () {
            refresh_newsletter();
        }, 60000);
    }

    if (wpnotif_newsletter_list.length && wpnotif_newsletter_list.is(":visible")) {
        update_newsletter_time();
    }

    jQuery(".modal_form_details").on('submit', function (e) {
        e.preventDefault();


        hideWPNotifMessage();
        var $this = jQuery(this);
        var modal = $this.closest('.wpnotif-modal');
        var data_replace = modal.attr('data-replace');
        var type = $this.data('type');

        var data = jQuery(this).serialize();

        if (modal.data('reset')) {
            data = data + "&edit_id=" + edit_id;
        }


        showLoader();

        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: data,
            success: function (res) {
                hideLoader();
                if (isJSON(res)) {
                    if (res.success == 0) {
                        showWPNotifErrorMessage(res.data.message);
                        return;
                    }
                    var data = res.data;
                    var modal = $this.closest(".wpnotif-modal");
                    var html = jQuery.parseHTML(data.html);

                    if (type == 'user_group') {
                        jQuery('input[name="user_group_id"]').val(data.gid);

                        modal.hide();
                        show_modal($this.data('show'), false);
                    }
                    jQuery("." + data_replace).html(html);
                    modal.find(".wpnotif-hide_modal").trigger('click');
                    $this.find('input[type="text"], textarea').val('');

                }
            }, error: function () {
                hideLoader();
                showWPNotifErrorMessage(nl.Error);
            }
        });

        return false;
    })

    jQuery(document).on('click', '.delete-newsletter', function (e) {
        e.preventDefault();
        hideWPNotifMessage();
        var row = jQuery(this).closest('tr');
        var nid = [];
        nid.push(row.attr('id'));
        delete_newsletter(nid);
    });

    function delete_newsletter(nids) {
        var action = "wpnotif_delete_newsletter";
        delete_data(action, nids);
    }

    function delete_data(action, nids) {
        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: "action=" + action + "&nids=" + nids.join(",") + "&newsletter_nonce=" + nl.nonce,
            success: function (res) {
                hideLoader();
                if (isJSON(res)) {
                    if (res.success == 0) {
                        showWPNotifErrorMessage(res.data.message);
                    } else {
                        jQuery.each(nids, function (index, nid) {
                            jQuery("#" + nid).fadeOut(function () {
                                jQuery(this).remove();
                            })
                        });
                    }
                }
            }, error: function () {
                hideLoader();
                showWPNotifErrorMessage(nl.Error);
            }
        });

    };


    jQuery(document).on('click', '.delete-usergroup', function (e) {
        var action = "wpnotif_delete_usergroup";
        showLoader();
        e.preventDefault();
        hideWPNotifMessage();
        var row = jQuery(this).closest('tr');
        var nid = [];
        nid.push(row.attr('id'));
        delete_data(action, nid);
    });

    jQuery(document).on('click', '.usergroup_open,.wpnotif_link', function (e) {
        location.href = jQuery(this).attr('href');
    });


    jQuery(document).on('click', '.newsletter_change_state', function (e) {

        var $this = jQuery(this);
        var nonce = $this.data('nonce');
        var row = $this.closest('tr');
        var nid = row.attr('id');
        var data = "action=" + $this.data('action') + "&nid=" + nid + "&state=" + $this.data('state') + "&newsletter_nonce=" + nonce;
        if (row.data('current-state')) {
            data = data + "&current_state=" + row.data('current-state');
        }
        change_state(data)
    });

    function change_state(data) {
        hideWPNotifMessage();
        showLoader();
        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: data,
            success: function (res) {
                hideLoader();
                if (isJSON(res)) {
                    if (res.success == 0) {
                        showWPNotifErrorMessage(res.data.message);
                    } else {
                        var data = res.data;

                        showWPNotifSuccessMessage(data.message);
                        var html = jQuery.parseHTML(data.html);
                        jQuery(".wpnotif-scheduled-newsletter_table").html(html);
                    }
                }
            }, error: function () {
                hideLoader();
                showWPNotifErrorMessage(nl.Error);
            }
        });
    }

    var check_group = jQuery(".create_predefined_group");
    if (check_group.length) {
        var groups = check_group.data('groups');
        create_predefined_groups(groups);
    }

    function create_predefined_groups(groups) {
        var group = groups.shift();

        if (!group) {
            location.reload();
            return false;
        }

        showLoader();
        _create_predefined_group(0, group, groups);
    }

    var is_group_msg_shown = false;

    function _create_predefined_group(request, group, groups) {

        if (!is_group_msg_shown) {
            is_group_msg_shown = true;
            showWPNotifSuccessMessage(nl.creatingPredefinedGroups);
        }

        var data = "action=wpnotif_create_predefined_group&group=" + group + "&request=" + request + "&newsletter_nonce=" + nl.nonce;

        jQuery.ajax({
            type: 'post',
            url: nl.ajax_url,
            data: data,
            success: function (res) {
                var success = res.success;

                if (success === false) {
                    hideLoader();
                    showWPNotifErrorMessage(res.data.message);
                } else {
                    var data = res.data;
                    if (data.type == 'remaining') {
                        request = data.request;
                        _create_predefined_group(request, group, groups);
                    } else {
                        create_predefined_groups(groups);
                    }
                }

            }, error: function () {
                is_group_msg_shown = false;
                showWPNotifNoticeMessage(nl.error_retrying);
                setTimeout(function () {
                    _create_predefined_group(request, group, groups);
                }, 3000);
            }
        });

    }


    var data_table = jQuery('#wpnotif_data_table');
    if (data_table.length) {
        jQuery.fn.dataTable.ext.errMode = 'none';

        var toolbar = jQuery(".wpnotif_admin_conf .table_buttons");

        var data_table_config = {
            "processing": true,
            "serverSide": true,
            "searchDelay": 500,
            "ajax": {
                "url": nl.ajax_url,
                "data": function (d) {
                    d.action = data_table.data('action');
                    d.id = data_table.data('id');
                    d.newsletter_nonce = data_table.data('nonce');

                    d.newsletter_nonce = data_table.data('nonce');

                    if (toolbar.length) {
                        toolbar.find('input,select').each(function (i, field) {
                            d[field.name] = field.value;
                        });
                    }


                },
                "type": "POST"
            },
            "order": [[1, "ASC"]],
            "pageLength": 50,
            "columns": data_table.data('coloumn'),
            "fnDrawCallback": function (oSettings) {
                hideLoader();
            },
            "fnPreDrawCallback": function (oSettings) {
                if (!document.activeElement) {
                    showLoader();
                }
                jQuery(".wpnotif_admin_conf .select_all").prop('checked', false).trigger('change');
            },
            "oLanguage": {
                sProcessing: "",
            }
        };
        if (data_table.data('disable_search')) {
            data_table_config['searching'] = false;
        }
        if (data_table.data('disable_sort')) {
            data_table_config['columnDefs'] = [{
                "targets": data_table.data('disable_sort'),
                "orderable": false
            }];
        }

        data_table.DataTable(data_table_config);


        if (toolbar.length) {
            jQuery(".dataTables_filter").prepend(toolbar);
            toolbar.on('change', function (e) {
                var target = e.target;
                if (target.id == 'show_user_type') {
                    var selected_button_cls = 'wpnotif_select_button';
                    jQuery(".user-list_modify").addClass('wpnotif-hide').removeClass(selected_button_cls);

                    if (target.value == 1) {
                        jQuery(".remove_user_from_list").removeClass(selected_button_cls);
                        jQuery(".add_user_to_list").addClass(selected_button_cls);
                    } else {
                        jQuery(".remove_user_from_list").addClass(selected_button_cls);
                        jQuery(".add_user_to_list").removeClass(selected_button_cls);
                    }
                }
                data_table.DataTable().draw();
            })
        }

    }


    jQuery(".wpnotif-schedule").datepicker({
        language: 'en',
        minDate: new Date(),
        timepicker: true,
        clearButton: true,
        timeFormat: ' hh:ii aa',
        multipleDatesSeparator: " - ",
        dateFormat: "M dd, yy",
        autoClose: false,
        onSelect: function (formattedDate, date, inst) {
            jQuery(inst.el).trigger('change');
        }
    });

    function isJSON(data) {
        if (typeof data != 'string')
            data = JSON.stringify(data);

        try {
            JSON.parse(data);
            return true;
        } catch (e) {
            return false;
        }
    }

    function showLoader() {
        loader.show();
    }

    function hideLoader() {
        loader.hide();
    }

    var system_time = undefined;
    var updateTimeInterval;
    var footer_time = jQuery('.wpnotif-footer_time');

    var date_options = {
        weekday: 'long',
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric'
    };

    if (footer_time.length) {
        var time = footer_time.data('time');
        system_time = new Date(time * 1000);

        updateSystemTime();
        footer_time.parent().removeClass('wpnotif-hide');
        updateTimeInterval = setInterval(updateSystemTime, 1000);
    }


    function updateSystemTime() {
        if (system_time == undefined) return;

        system_time.setSeconds(system_time.getSeconds() + 1);
        footer_time.text(system_time.toLocaleDateString("en-US", date_options));
    }
});