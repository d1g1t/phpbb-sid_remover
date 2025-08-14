<?php
/**
 *
 * SID Remover. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2025, d1g1t
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace d1g1t\sid_remover\event;

/**
 * @ignore
 */
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event listener.
 */
class main_listener implements EventSubscriberInterface
{
	public static function getSubscribedEvents()
	{
		return array(
			'core.append_sid' => 'remove_sid', 	//Get rid of sids
		);
	}

	protected $user;

	public function __construct(\phpbb\user $user) {
		$this->user = $user;
	}

	public function remove_sid($event) {
		//Args: append_sid_overwrite, is_amp, is_route, params, session_id, url
		//Do not meddle if NEED_SID is set
		if(defined('NEED_SID')) return;
		//New url needs to be written to this
		$event['append_sid_overwrite'] = $this->append_sid($event);
	}

	private function append_sid($event) {
		//Globals that the default append_sid() uses
		global $_SID, $_EXTRA_URL;

		//Map vars so that original append_sid() code can be copy pasted
		$url = $event['url'];
		$params = $event['params'];
		$is_amp = $event['is_amp'];
		$session_id = $event['session_id'];

		//Original code from functions.php
		$params_is_array = is_array($params);

		// Get anchor
		$anchor = '';
		if (strpos($url, '#') !== false)
		{
			list($url, $anchor) = explode('#', $url, 2);
			$anchor = '#' . $anchor;
		}
		else if (!$params_is_array && strpos($params, '#') !== false)
		{
			list($params, $anchor) = explode('#', $params, 2);
			$anchor = '#' . $anchor;
		}

		// Handle really simple cases quickly
		if ($_SID == '' && $session_id === false && empty($_EXTRA_URL) && !$params_is_array && !$anchor)
		{
			if ($params === false)
			{
				return $url;
			}

			$url_delim = (strpos($url, '?') === false) ? '?' : (($is_amp) ? '&amp;' : '&');
			return $url . ($params !== false ? $url_delim. $params : '');
		}

		// Assign sid if session id is not specified
		if ($session_id === false)
		{
			$session_id = $_SID;
		}

		$amp_delim = ($is_amp) ? '&amp;' : '&';
		$url_delim = (strpos($url, '?') === false) ? '?' : $amp_delim;

		// Appending custom url parameter?
		$append_url = (!empty($_EXTRA_URL)) ? implode($amp_delim, $_EXTRA_URL) : '';

		// Use the short variant if possible ;)
		if ($params === false)
		{
			// Append session id
			if (!$session_id)
			{
				return $url . (($append_url) ? $url_delim . $append_url : '') . $anchor;
			}
			else
			{
				#Change suggested in https://www.phpbb.com/community/viewtopic.php?p=16066738#p16066738
				if ($this->user->data['is_registered'] && !$this->user->data['is_bot'])
				{
					return $url . (($append_url) ? $url_delim . $append_url . $amp_delim : $url_delim) . 'sid=' . $session_id . $anchor;
				}
				else {
					return $url . (($append_url) ? $url_delim . $append_url . "" : "") . $anchor;
				}
			}
		}

		// Build string if parameters are specified as array
		if (is_array($params))
		{
			$output = array();

			foreach ($params as $key => $item)
			{
				if ($item === NULL)
				{
					continue;
				}

				if ($key == '#')
				{
					$anchor = '#' . $item;
					continue;
				}

				$output[] = $key . '=' . $item;
			}

			$params = implode($amp_delim, $output);
		}

		// Append session id and parameters (even if they are empty)
		// If parameters are empty, the developer can still append his/her parameters without caring about the delimiter
		#Change suggested in https://www.phpbb.com/community/viewtopic.php?p=16066738#p16066738
		if ($session_id && ($this->user->data['is_registered'] && !$this->user->data['is_bot']))
		{
			return $url . (($append_url) ? $url_delim . $append_url . $amp_delim : $url_delim) . $params . ((!$session_id) ? '' : $amp_delim . 'sid=' . $session_id) . $anchor;
		}
		else
		{
			return $url . (($append_url) ? $url_delim . $append_url . $amp_delim : $url_delim) . $params . $anchor;
		}
	}
}
