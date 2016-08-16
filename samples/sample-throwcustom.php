<?php



class myException extends Exception
{

}


try {
	throw new myException;
}
catch(myException $e)
{

	var_dump("good");
	var_dump(get_class($e));
}
catch (Exception $e)
{
	var_dump("yoyo");
}

var_dump("done");
