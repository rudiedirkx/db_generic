<?php

class RuleKeyedModel extends db_generic_model {
	static public $_table = 'keyed';
}

class RuleKeyedWithoutId extends db_generic_model {
	static public $_table = 'pivot';
}

class RuleKeylessModel extends db_generic_keyless_model {
	static public $_table = 'pivot';
}

class RuleKeylessWithId extends db_generic_keyless_model {
	static public $_table = 'keyed';
}

class RuleUnknownTable extends db_generic_model {
	static public $_table = 'unknown';
}

abstract class RuleAbstractModel extends db_generic_model {
}
