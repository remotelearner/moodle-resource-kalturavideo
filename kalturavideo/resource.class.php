<?php
defined('MOODLE_INTERNAL') || die();

class resource_kalturavideo extends resource_base {

    function resource_kalturavideo($cmid = 0) {
        global $COURSE, $CFG;
        require_once($CFG->dirroot.'/blocks/kaltura/lib.php');
        require_js($CFG->wwwroot.'/blocks/kaltura/js/jquery.js');
        require_js($CFG->wwwroot.'/blocks/kaltura/js/kvideo.js');
        require_js($CFG->wwwroot.'/blocks/kaltura/js/swfobject.js');

        parent::resource_base($cmid);

        $this->release  = '1.2';

        // Add Kaltura block instance (needed for backup and restor purposes)
        $blockid = get_field('block', 'id', 'name', 'kaltura');

        if ($blockid) {
            if (!record_exists('block_instance', 'pageid', $COURSE->id, 'blockid', $blockid)) {

                $block              = new stdClass();
                $block->blockid     = $blockid;
                $block->pageid      = $COURSE->id;
                $block->pagetype    = 'course-view';
                $block->position    = 'r';
                $block->weight      = '3';
                $block->visible     = 0;
                $block->configdata  = 'Tjs=';

                insert_record('block_instance', $block);
            }
        }

    }

    function display() {

        global $CFG;

        $formatoptions = new object();
        $formatoptions->noclean = true;

        require_js($CFG->wwwroot . '/blocks/kaltura/js/flashversion.js');
        require_js($CFG->wwwroot . '/blocks/kaltura/js/kdp_flash_ver_tester.js');
        require_js($CFG->wwwroot . '/blocks/kaltura/js/kaltura.lib.js');

        /// Are we displaying the course blocks?
        if (0 == strcmp($this->resource->options, 'showblocks')) {

            parent::display_course_blocks_start();

            $entry = get_record('block_kaltura_entries', 'context', "R_" . "$this->resource->id");

            if (trim(strip_tags($this->resource->alltext))) {
                echo $entry->title;

                $context = get_context_instance(CONTEXT_COURSE, $this->course->id);
                $formatoptions = new object();
                $formatoptions->noclean = true;

                if (trim(strip_tags($resource->summary))) {
                    print_simple_box(format_text($resource->summary, FORMAT_MOODLE, $formatoptions, $this->course->id), "center");
                }

                if (has_capability('moodle/course:manageactivities',$context)) { //check if admin of this widget

                    echo embed_kaltura($resource->alltext,get_width($entry),get_height($entry),$entry->entry_type, $entry->design, true);
                } else {

                    echo embed_kaltura($resource->alltext,get_width($entry),get_height($entry),KalturaEntryType::MEDIA_CLIP, $entry->design, true);
                }
            }

            parent::display_course_blocks_end();

        } else {

            /// Set up generic stuff first, including checking for access
            parent::display();

            /// Set up some shorthand variables
            $cm = $this->cm;
            $course = $this->course;
            $resource = $this->resource;

            $entry = get_record('block_kaltura_entries','context',"R_" . "$resource->id");

            $pagetitle = strip_tags($course->shortname.': '.format_string($resource->name));
            $inpopup = optional_param('inpopup', '', PARAM_BOOL);

            add_to_log($course->id, 'resource', 'view', "view.php?id={$cm->id}", $resource->id, $cm->id);
            $navigation = build_navigation($this->navlinks, $cm);

            // Print Header
            print_header($pagetitle, $course->fullname, $navigation,
                         '', '', true, update_module_button($cm->id, $course->id, $this->strresource),
                         navmenu($course, $cm));

            // Print caching notification
            list($notify, $minutes) = is_video_cached($resource->timemodified);

            if ($notify and ($minutes > 0) ) {
                echo notify(get_string('videocache', 'block_kaltura', $minutes), 'notifyproblem', 'center', true);
            }


            if (trim(strip_tags($this->resource->alltext))) {
                echo $entry->title;

                $context = get_context_instance(CONTEXT_COURSE, $this->course->id);
                $formatoptions = new object();
                $formatoptions->noclean = true;

                if (trim(strip_tags($resource->summary))) {
                    print_simple_box(format_text($resource->summary, FORMAT_MOODLE, $formatoptions, $this->course->id), "center");
                }

                if (has_capability('moodle/course:manageactivities',$context)) { //check if admin of this widget

                    echo embed_kaltura($resource->alltext, get_width($entry), get_height($entry), $entry->entry_type, $entry->design, true);

                } else {

                    echo embed_kaltura($resource->alltext, get_width($entry), get_height($entry), $entry->entry_type, $entry->design, true);
                }
            }

            $strlastmodified = get_string("lastmodified");
            echo "<div class=\"modified\">$strlastmodified: ".userdate($resource->timemodified)."</div>";

            print_footer($course);

        }
    }

    function add_instance($resource) {

        $result = parent::add_instance($resource);


        if (false !== $result) {

            $dimensions     = optional_param('dimensions', '', PARAM_NOTAGS);
            $size           = optional_param('size', '', PARAM_NOTAGS);
            $custom_width   = optional_param('custom_width', '', PARAM_NOTAGS);
            $design         = optional_param('design', '', PARAM_NOTAGS);
            $title          = optional_param('title', '', PARAM_NOTAGS);
            $entry_type     = optional_param('entry_type', '', PARAM_NOTAGS);

            $entry                  = new kaltura_entry;
            $entry->courseid        = $resource->course;
            $entry->entry_id        = $resource->alltext;
            $entry->dimensions      = $dimensions;
            $entry->size            = $size;
            $entry->custom_width    = $custom_width;
            $entry->design          = $design;
            $entry->title           = $title;
            $entry->context         = 'R_' . $result;
            $entry->entry_type      = $entry_type;
            $entry->media_type      = KalturaMediaType::VIDEO;

            $entry->id = insert_record('block_kaltura_entries', $entry);
        }

        return $result;
    }

    function update_instance($resource) {

        $time           = time();

        $result = parent::update_instance($resource);

        if (false !== $result) {

            $dimensions     = optional_param('dimensions', '', PARAM_NOTAGS);
            $size           = optional_param('size', '', PARAM_NOTAGS);
            $custom_width   = optional_param('custom_width', '', PARAM_NOTAGS);
            $design         = optional_param('design', '', PARAM_NOTAGS);
            $title          = optional_param('title', '', PARAM_NOTAGS);

            $entry                  = get_record('block_kaltura_entries', 'context', "R_" . "$resource->instance");
            $entry->entry_id        = $resource->alltext;
            $entry->dimensions      = $dimensions;
            $entry->size            = $size;
            $entry->custom_width    = $custom_width;
            $entry->design          = $design;
            $entry->title           = $title;

            update_record('block_kaltura_entries', $entry);

        }

        return $result;
    }

    function delete_instance($resource) {

        delete_records('block_kaltura_entries','context',"R_" . "$resource->id");
        return parent::delete_instance($resource);
    }

    function setup_elements(&$mform) {

        global $CFG;

        $default_entry = new stdClass();
        $partner_id = KalturaHelpers::getPlatformKey('kaltura_partner_id', 'none');

        if (0 == $partner_id) {
            print_error('blocknotinitialized', 'block_kaltura');
            die();
        }

        $updateparam = optional_param('update', '', PARAM_INT);

        if (!empty($updateparam)) {

            $item_id = $updateparam;

            $result = get_record('course_modules', 'id', $item_id);
            $result = get_record('resource','id',$result->instance);
            $entry  = get_record('block_kaltura_entries','context',"R_" . "$result->id");
            $default_entry = $entry;

        } else {

            $last_entry_id = get_field('block_kaltura_entries','max(id)', 'id', 'id');

            $sql = "SELECT * FROM {$CFG->prefix}block_kaltura_entries ORDER BY id DESC";
            $default_entry = get_record_sql($sql, true);

            if (!empty($default_entry)) {

                $default_entry->title = '';
            } else {

                $default_entry = new kaltura_entry;
            }
        }

        $hidden_alltext = new HTML_QuickForm_hidden('alltext', '', array('id' => 'id_alltext'));
        $mform->addElement($hidden_alltext);

        $hidden_popup = new HTML_QuickForm_hidden('popup', '', array('id' => 'id_popup'));
        $mform->addElement($hidden_popup);

        $hidden_dimensions = new HTML_QuickForm_hidden('dimensions', $default_entry->dimensions, array('id' => 'id_dimensions'));
        $mform->addElement($hidden_dimensions);

        $hidden_size = new HTML_QuickForm_hidden('size', $default_entry->size, array('id' => 'id_size'));
        $mform->addElement($hidden_size);

        $hidden_custom_width = new HTML_QuickForm_hidden('custom_width', $default_entry->custom_width, array('id' => 'id_custom_width'));
        $mform->addElement($hidden_custom_width);

        $hidden_design = new HTML_QuickForm_hidden('design', $default_entry->design, array('id' => 'id_design'));
        $mform->addElement($hidden_design);

        $hidden_title = new HTML_QuickForm_hidden('title', $default_entry->title, array('id' => 'id_title'));
        $mform->addElement($hidden_title);

        $hidden_entry_type = new HTML_QuickForm_hidden('entry_type', $default_entry->entry_type, array('id' => 'id_entry_type'));
        $mform->addElement($hidden_entry_type);

        $text_video = new HTML_QuickForm_static('video_text',null,
                      '<span id="spanExplain"><table style="width:100%;font-size:9px;"><tr><td id="non_edit_col" width="25%">'.
                      get_string('videotext', 'resource_kalturavideo').'</td><td id="edit_col" style="width:40%;padding-left:25px;">'.
                      get_string('videoremixtext', 'resource_kalturavideo').'</td><td width="35%">&nbsp;</td></tr></table></span>');


        $button = new HTML_QuickForm_input;
        $button->setName('addvid');
        $button->setType('button');
        $button->setValue('Add Video');

        $button_editable = new HTML_QuickForm_input;
        $button_editable->setName('addeditvid');
        $button_editable->setType('button');
        $button_editable->setValue('Add Editable Video');

        $button_replace = new HTML_QuickForm_input;
        $button_replace->setName('replacevid');
        $button_replace->setType('button');
        $button_replace->setValue('Replace Video');

        $button_preview = new HTML_QuickForm_input;
        $button_preview->setName('previewvid');
        $button_preview->setType('button');
        $button_preview->setValue('Preview Video');

        $button_preview_edit = new HTML_QuickForm_input;
        $button_preview_edit->setName('previeweditvid');
        $button_preview_edit->setType('button');
        $button_preview_edit->setValue('Preview & Edit Video');

        $videolabel         = get_string('addvideo', 'resource_kalturavideo');
        $videoeditablelabel = get_string('editablevideo', 'resource_kalturavideo');
        $replacelabel       = get_string('replacevideo', 'resource_kalturavideo');
        $previewlabel       = get_string('previewvideo', 'resource_kalturavideo');
        $previeweditlabel   = get_string('previeweditvideo', 'resource_kalturavideo');

        $cw_url         = $CFG->wwwroot . '/blocks/kaltura/kcw.php?';
        $cw_url_init    = $cw_url;

        $edit_url       = $CFG->wwwroot . '/blocks/kaltura/keditor.php?';
        $edit_url_init  = $edit_url;

        $preview_url        = $CFG->wwwroot . '/blocks/kaltura/kpreview.php?';
        $preview_url_init   = $preview_url;
        $preview_entry_id   = '';

        $upload_type_video  = '';
        $upload_type_mix    = '';

        if (!empty($entry)) {

            $cw_url_init        .= 'id=' . $entry->id;

            $upload_type_video  = '&upload_type=video';
            $upload_type_mix    = '&upload_type=mix';

            $preview_url_init   .= 'entry_id=\' + value ' . '+ \'&design=' . $entry->design . '&width=' . get_width($entry) . '&dimensions=' . $entry->dimensions;
            $preview_entry_id    = "var value = document.getElementById('id_alltext').value;";

            $edit_url_init      .= 'entry_id=' . $entry->entry_id;


        } else {

            $upload_type_video  = 'upload_type=video';
            $upload_type_mix    = 'upload_type=mix';
        }

        // RL - EDIT
        $button_attributes = array(
            'type' => 'button',
            'onclick' => 'set_entry_type('.KalturaEntryType::MEDIA_CLIP.');kalturaInitModalBox(\''. $cw_url_init . $upload_type_video .'\', {width:763, height:433});',
            'id' => 'id_addvideo',
            'value' => $videolabel,
            'style' => (empty($entry) ? 'display:inline' : 'display:none'),
        );

        $button_attributes_editable = array(
            'type' => 'button',
            'onclick' => 'set_entry_type('.KalturaEntryType::MIX.');kalturaInitModalBox(\''. $cw_url_init .  $upload_type_mix .'\', {width:763, height:433});',
            'id' => 'id_addeditablevideo',
            'value' => $videoeditablelabel,
            'style' => (empty($entry) ? 'display:inline;margin-left:90px;' : 'display:none'),
        );
        // RL - EDIT END

        $upload_type = '';

        if (!empty($entry)) {
            $upload_type = ($entry->entry_type == KalturaEntryType::MEDIA_CLIP) ? '&upload_type=video' : '&upload_type=mix';
        }

        $button_attributes_replace = array(
            'type' => 'button',
            'onclick' => 'kalturaInitModalBox(\''. $cw_url_init . $upload_type . '\', {width:763, height:433});',
            'id' => 'id_replace',
            'value' => $replacelabel,
            'style' => (empty($entry) ? 'display:none' : 'display:inline'),
        );

        $button_attributes_preview = array(
            'type' => 'button',
            'onclick' => $preview_entry_id . 'kalturaInitModalBox(\''. $preview_url_init .'\', ' . (empty($entry) ? '{width:405, height:382}' : ('{width:' . (get_width($entry)) . ', height:' . (get_height($entry)+50) . '}')) . ');',
            'id' => 'id_preview',
            'value' => $previewlabel,
            'style' => ((empty($entry) || $entry->entry_type != KalturaEntryType::MEDIA_CLIP) ? 'display:none' : 'display:inline'),
        );

        $button_attributes_preview_edit = array(
            'type' => 'button',
            'onclick' => 'kalturaInitModalBox(\''. $edit_url_init .'\', {width:890, height:546});',
            'id' => 'id_preview_edit',
            'value' => $previeweditlabel,
            'style' => ((empty($entry) || $entry->entry_type != KalturaEntryType::MIX) ? 'display:none' : 'display:inline'),
        );

        $resource = $this->resource;


        $thumbnail = "";

        if (!empty($updateparam)) {
            if(!empty($entry)) {
                $thumbnail = '<img id="id_thumb" src="'. KalturaHelpers::getThumbnailUrl(null, $entry->entry_id, 140, 105) .'" />';
    //        $mform->addElement('static', 'video_thumb', get_string('video', 'resource_kalturavideo'), $thumbnail);
            }
        }

        $button->setAttributes($button_attributes);
        $button_editable->setAttributes($button_attributes_editable);
        $button_replace->setAttributes($button_attributes_replace);
        $button_preview->setAttributes($button_attributes_preview);
        $button_preview_edit->setAttributes($button_attributes_preview_edit);

        $objs = array();
        $objs[] = &$button;
        $objs[] = &$button_editable;
        $objs[] = &$button_replace;
        $objs[] = &$button_preview;
        $objs[] = &$button_preview_edit;


        $text_objs = array();
        $text_objs[] = $text_video;

        $divWait = '<div style="border:1px solid #bcbab4; background-color:#f5f1e9; '.
                    'width:140px; height:105px; float:left; text-align:center;'.
                    'font-size:85%; display:' . (empty($thumbnail) ? 'none;' : 'inline;') .
                    '" id="divWait">' . $thumbnail .'</div>';


        // Initialized kaltura javascript main variables
        require_js($CFG->wwwroot . '/blocks/kaltura/js/kaltura.main.js');

        $kalturaprotal = new kaltura_jsportal();
        $jsoutput = $kalturaprotal->print_javascript(
                             array(
                              'aspecttype'  => KalturaAspectRatioType::ASPECT_4_3,
                              'sizelarge'   => KalturaPlayerSize::LARGE,
                              'sizesmall'   => KalturaPlayerSize::SMALL,
                              'sizecustom'  => KalturaPlayerSize::CUSTOM,
                              'mediaclip'   => KalturaEntryType::MEDIA_CLIP,
                              'preview_url' => $preview_url,
                              'edit_url'    => $edit_url,
                              'cw_url'      => $cw_url,
                            ), false, true);

        require_js($CFG->wwwroot . '/blocks/kaltura/js/kaltura.lib.js');
        require_js($CFG->wwwroot . '/blocks/kaltura/js/videoresource.js');

        $divwait_contents           = get_wait_image('divWait', 'id_alltext', false, true);
        $please_wait_contents       = empty($entry) ? '' : get_string('video', 'resource_kalturavideo');
        $videogroup__contents       = empty($entry) ? get_string('video', 'resource_kalturavideo') : '';

        $mform->addElement('static', 'divWait', '', $divwait_contents);
        $mform->addElement('static', 'please_wait', $please_wait_contents, $divWait);
        $mform->addElement('group', 'videogroup', $videogroup__contents, $objs);

        $mform->addElement('static', 'jsblock', '', $jsoutput);


        if (empty($updateparam)) {
            $mform->addElement('group','videotextgroup', '',$text_objs);
        }

        $mform->addElement('header', 'displaysettings', get_string('display', 'resource'));

        return;

    }
}
?>