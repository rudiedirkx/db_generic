<?php

class EscapeTest extends DbTestCase {

	public function testEscapeAndQuoteValue() : void {
		$this->assertSame("'abc'", $this->db->escapeAndQuoteValue('abc'));
	}

	public function testEscapeAndQuoteValueNull() : void {
		$this->assertSame('NULL', $this->db->escapeAndQuoteValue(null));
	}

	public function testEscapeAndQuoteValueEscapesQuotes() : void {
		$this->assertSame("'O''Brien'", $this->db->escapeAndQuoteValue("O'Brien"));
	}

	public function testEscapeAndQuoteValueBool() : void {
		$this->assertSame("'1'", $this->db->escapeAndQuoteValue(true));
		$this->assertSame("'0'", $this->db->escapeAndQuoteValue(false));
	}

	public function testEscapeAndQuoteColumn() : void {
		$this->assertSame('"name"', $this->db->escapeAndQuoteColumn('name'));
	}

	public function testReplaceholders() : void {
		$sql = $this->db->replaceholders('age > ? AND name = ?', [18, 'Bob']);
		$this->assertSame("age > '18' AND name = 'Bob'", $sql);
	}

	public function testReplaceholdersWithArray() : void {
		$sql = $this->db->replaceholders('id IN (?)', [[1, 2, 3]]);
		$this->assertSame("id IN ('1', '2', '3')", $sql);
	}
}
