<?php

class RequestHandler
{
	static $base;
	static $selector;


	public function RequestHandler()
	{
		throw new Exception("RequestHandler is a static class");
	}

	public static function GetBaseDirectory()
	{
		static $baseDirectory;

		if (!isset($baseDirectory))
			$baseDirectory = dirname(__FILE__) . '/../';

		return $baseDirectory;
	}

	public static function GetSelectorName()
	{
		if (!isset($selector))
			self::$selector = $_REQUEST['__select'];

		if (empty(self::$selector))
			self::$selector = 'index';

		return self::$selector;
	}

	public static function getServiceRegistry($selector)
	{
		$serviceRegistry = parse_ini_file(RequestHandler::GetBaseDirectory() . "/ini/serviceRegistry.ini", true);

		if(array_key_exists($selector, $serviceRegistry))
		{
			return $serviceRegistry[$selector];
		}

		return null;
	}
	public static function removeSlash($selector)
	{
		if(strpos('/', $selector) == 0)
		{
			return substr($selector, 1, strlen ( $selector ) -1);
		}
		else
			return $selector;
	}
	public static function CheckSelectorService($selector)
	{
		return self::GetBaseDirectory() . '/handlers/' . ($selector === false ? self::GetSelectorName() : $selector). '.php';
	}

	public static function splitUp($str)
	{
		return explode("@", $str);
	}
	public static function GetSelectorFile($selector)
	{
		$selector = self::removeSlash($selector);
		// look in config file for selected service

		// if service exists proceed
		$registry = self::getServiceRegistry($selector);

		if($registry && isset($registry[$_SERVER['REQUEST_METHOD']]))
		{
			trigger_error("Service $selector Registered and Request Method ".$_SERVER['REQUEST_METHOD']." registered");
			// service has been registered and request method is allowed, now check if it exists
			
			//try
			//{
				// split the method up
				$caller = self::splitUp($registry[$_SERVER['REQUEST_METHOD']]);

				if (file_exists(self::GetBaseDirectory()."/services/".$selector."/controllers/".$caller.".php"))
				{
					//header('Cache-Control: no-cache');
					//header('Pragma: no-cache');

					//include ($inc_file);
				}
				else
				{

					print "Service or controller does not exist";		

				}
			//}
			//catch

		}

		//throw new ServiceNotFoundException("$selector");
	}

	public static function SetSelector($newSelector)
	{
		self::$selector = $newSelector;
	}

	public static function GetParams()
	{
		return $_REQUEST['__param'];
	}

	public static function ExecuteRequest($selector = false)
	{
		if ($selector === false)
			$selector = self::GetSelectorName();

		$inc_file = self::GetSelectorFile($selector);

		ob_end_clean();
		ob_start();

		try
		{
			if (file_exists($inc_file))
			{
				header('Cache-Control: no-cache');
				header('Pragma: no-cache');

				include ($inc_file);
			}
			else
				throw new FileNotFoundException("$selector");
		}
		catch (BadRequestException $brex)
		{
			trigger_error("Invalid request: " . $brex->getMessage(), E_USER_WARNING);

			if ($selector != '400')
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
				self::ExecuteRequest('400');
				exit;
			}
		}
		catch (FileNotFoundException $fofex)
		{
			//trigger_error("Invalid selector: " . $fofex->getMessage(), E_USER_WARNING);

			if ($selector != '404')
			{
				header($_SERVER['SERVER_PROTOCOL'] . ' 404 File Not Found');
				self::ExecuteRequest('404');
				exit;
			}
		}
		catch (FoundElsewhereRedirectException $ferex)
		{
			// exception message contains the URL to be redirected to
			header($_SERVER['SERVER_PROTOCOL'] . ' 302 Found Elsewhere');
			header('Location: ' . $ferex->getMessage());
			exit;
		}
		catch (RedirectException $rex)
		{
			// exception message contains the URL to be redirected to
			header($_SERVER['SERVER_PROTOCOL'] . ' 301 Moved Permanently');
			header('Location: ' . $rex->getMessage());
			exit;
		}
		catch (TemporaryRedirectException $trex)
		{
			// exception message contains the URL to be redirected to
			header($_SERVER['SERVER_PROTOCOL'] . ' 307 Moved Temporarily');
			header('Location: ' . $trex->getMessage());
			exit;
		}
		catch (Exception $ex)
		{
			trigger_error("Exception: " . $ex->getMessage(), E_USER_WARNING);

			if ($selector != 'error')
			{
				self::ExecuteRequest('error');
				exit;
			}

			print "An error has occurred, please try again later.";
		}

		while(ob_get_level() > 0)
			ob_end_flush();
	}
}

class ServiceNotFoundException extends Exception{}
class BadRequestException extends Exception {}
class FileNotFoundException extends Exception {}
class RedirectException extends Exception {}
class FoundElsewhereRedirectException extends Exception {}
class TemporaryRedirectException extends Exception {}

?>
