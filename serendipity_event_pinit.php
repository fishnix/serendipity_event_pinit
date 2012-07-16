<?php 

/*
    Pinit Plugin for Serendipity
    E. Camden Fisher <fishnix@gmail.com>
*/

if (IN_serendipity != true) {
    die ("Don't hack!"); 
}
    
$time_start = microtime(true);

// Probe for a language include with constants. Still include defines later on, if some constants were missing
$probelang = dirname(__FILE__) . '/' . $serendipity['charset'] . 'lang_' . $serendipity['lang'] . '.inc.php';

if (file_exists($probelang)) {
    include $probelang;
}

include_once dirname(__FILE__) . '/lang_en.inc.php';

class serendipity_event_pinit extends serendipity_event
{

    function example() 
    {
      echo PLUGIN_EVENT_PINIT_INSTALL;
    }

    function introspect(&$propbag)
    {
        global $serendipity;

        $propbag->add('name',         PLUGIN_EVENT_PINIT_NAME);
        $propbag->add('description',  PLUGIN_EVENT_PINIT_DESC);
        $propbag->add('stackable',    false);
        $propbag->add('groups',       array('IMAGES'));
        $propbag->add('author',       'E Camden Fisher <fish@fishnix.net>');
        $propbag->add('version',      '0.0.1');
        $propbag->add('requirements', array(
            'serendipity' => '1.5.0',
            'smarty'      => '2.6.7',
            'php'         => '5.2.0'
        ));

      // make it cacheable
      $propbag->add('cachable_events', array(
          'frontend_display' => true
        ));
            
      $propbag->add('event_hooks',   array(
          'frontend_display' => true
        ));

      $this->markup_elements = array(
          array(
            'name'     => 'ENTRY_BODY',
            'element'  => 'body',
          ),
          array(
            'name'     => 'EXTENDED_BODY',
            'element'  => 'extended',
          ),
          array(
            'name'     => 'HTML_NUGGET',
            'element'  => 'html_nugget',
          )
      );

        $conf_array = array();

        foreach($this->markup_elements as $element) {
            $conf_array[] = $element['name'];
        }

        $conf_array[] = 'using_pinit';
        $conf_array[] = 'pinit_account_id';

        $propbag->add('configuration', $conf_array);
    }

    function generate_content(&$title) {
      $title = $this->title;
    }

    function introspect_config_item($name, &$propbag) {
      switch($name) {
        case 'using_pinit':
          $propbag->add('name',           PLUGIN_EVENT_PINIT_PROP_PINIT_ON);
          $propbag->add('description',    PLUGIN_EVENT_PINIT_PROP_PINIT_ON_DESC);
          $propbag->add('default',        'true');
          $propbag->add('type',           'boolean');
        break;
        case 'pinit_account_id':
          $propbag->add('name',           PLUGIN_EVENT_PINIT_PROP_ACCOUNT);
          $propbag->add('description',    PLUGIN_EVENT_PINIT_PROP_ACCOUNT_DESC);
          $propbag->add('default',        '');
          $propbag->add('type',           'string');
        break;
        default:
          return false;
        break;
        
      }
      
      return true;
    }
    
    /*
     *
     * install plugin
     *
     */
    function install() {
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);
    }

    /*
     *
     * uninstall plugin
     *
     */
    function uninstall() {
        serendipity_plugin_api::hook_event('backend_cache_purge', $this->title);
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);
    }

    function cleanup() {
        global $serendipity;

        // we should rebuild the cache if we change configs
        serendipity_plugin_api::hook_event('backend_cache_purge', $this->title);
        serendipity_plugin_api::hook_event('backend_cache_entries', $this->title);

    }

    function event_hook($event, &$bag, &$eventData) {
      global $serendipity;
        
      $hooks = &$bag->get('event_hooks');
        
      if (isset($hooks[$event])) {
        switch($event) {
          case 'frontend_display':           
            // only burn cycles if enabled...
            if ($this->get_config('using_pinit')) {
              foreach ($this->markup_elements as $temp) {
                if (serendipity_db_bool($this->get_config($temp['name'], true)) && isset($eventData[$temp['element']])) {
                  $element = $temp['element'];
                                    
                  //$text = "";
                  //foreach ($eventData as $i => $value) {
                  //    $text = $text. $i.':'.$value.'<br />';
                  //}

                  //$eventData[$element] = 'Stuff: ' . $text;
                  $eventData[$element] =  $this->s9y_pinit_munge($eventData[$element], $eventData['title'], $eventData['id'], $eventData['timestamp']);
                }
              }
            }
            return true;
            break;
          default:
            return false;
          } 
      } else {
        return false;
      }
    }

		/*
     *	munge text and insert pinit tags
		 */
    function s9y_pinit_munge($body, $title, $id, $ts) {
  		global $serendipity;
  		
  		$posturl = $serendipity['baseURL'] . serendipity_archiveURL($id, $title, 'serendipityHTTPPath', true, array('timestamp' => $ts));
      $pinspan = '<span class="pinterest-button">';
      $pinurl = '<a href="http://pinterest.com/pin/create/button/?url='.$posturl.'&media=';
      $pindescription = '&description=' . urlencode($title);
      $pinfinish = '" class="pin-it"></a>';
      $pinend = '</span>';
      $pattern = '/(<!-- s9ymdb:\d+ -->)\s*<img(.*?)src="(.*?).(bmp|gif|jpeg|jpg|png)"(.*?) \/>/i';
      $replacement = $pinspan.$pinurl.$serendipity['baseURL'].'$3.$4'.$pindescription.$pinfinish.'$1<img$2src="$3.$4" $5 />'.$pinend;
      $body = preg_replace( $pattern, $replacement, $body );

      //Fix the link problem
      //$newpattern = '/<a(.*?)><span class="pinterest-button"><a(.*?)><\/a><img(.*?)\/><\/span><\/a>/i';
      //$replacement = '<span class="pinterest-button"><a$2></a><a$1><img$3\/></a></span>';
      //$body = preg_replace( $newpattern, $replacement, $body );
      
      // return munged text
      return $body;
    }
    
    function outputMSG($status, $msg) {
        switch($status) {
            case 'notice':
                echo '<div class="serendipityAdminMsgNotice"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_note.png') . '" alt="" />' . $msg . '</div>' . "\n";
                break;

            case 'error':
                echo '<div class="serendipityAdminMsgError"><img style="width: 22px; height: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_error.png') . '" alt="" />' . $msg . '</div>' . "\n";
                break;

            default:
            case 'success':
                echo '<div class="serendipityAdminMsgSuccess"><img style="height: 22px; width: 22px; border: 0px; padding-right: 4px; vertical-align: middle" src="' . serendipity_getTemplateFile('admin/img/admin_msg_success.png') . '" alt="" />' . $msg . '</div>' . "\n";
                break;
        }
    }
}

?>