<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser\Tests;

use CihanSenturk\OfxParser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Test amount parsing improvements
 * Tests for the integer detection fix (100 → 100.0 not 1.0)
 * 
 * @covers OfxParser\Ofx
 */
class AmountParsingTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Test integer amount parsing (e.g., "100" should become 100.0, not 1.0)
     */
    public function testIntegerAmountParsing(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>100
<FITID>TX001
<NAME>Integer Amount Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);
        
        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);
        
        $this->assertNotEmpty($account->statement->transactions);
        $transaction = reset($account->statement->transactions);
        
        // The bug was: "100" was parsed as 1.0 instead of 100.0
        $this->assertEquals(100.0, $transaction->amount, 'Integer amount should be 100.0');
        $this->assertEquals(1000.0, $account->balance, 'Balance should be 1000.0');
    }

    /**
     * Test decimal amount parsing
     */
    public function testDecimalAmountParsing(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>123.45
<FITID>TX001
<NAME>Decimal Amount Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>9876.54
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);
        
        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);
        
        $this->assertNotEmpty($account->statement->transactions);
        $transaction = reset($account->statement->transactions);
        
        $this->assertEquals(123.45, $transaction->amount);
        $this->assertEquals(9876.54, $account->balance);
    }

    /**
     * Test negative amount parsing
     */
    public function testNegativeAmountParsing(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20231015
<TRNAMT>-250
<FITID>TX001
<NAME>Negative Integer Amount
</STMTTRN>
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20231016
<TRNAMT>-99.99
<FITID>TX002
<NAME>Negative Decimal Amount
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>-500.50
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);
        
        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);
        
        $this->assertCount(2, $account->statement->transactions);
        
        $transactions = $account->statement->transactions;
        $this->assertEquals(-250.0, $transactions[0]->amount, 'Negative integer should be -250.0');
        $this->assertEquals(-99.99, $transactions[1]->amount, 'Negative decimal should be -99.99');
        $this->assertEquals(-500.50, $account->balance, 'Negative balance should be -500.50');
    }

    /**
     * Test zero amount parsing
     */
    public function testZeroAmountParsing(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>OTHER
<DTPOSTED>20231015
<TRNAMT>0
<FITID>TX001
<NAME>Zero Amount
</STMTTRN>
<STMTTRN>
<TRNTYPE>OTHER
<DTPOSTED>20231016
<TRNAMT>0.00
<FITID>TX002
<NAME>Zero Decimal Amount
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>0
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);
        
        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);
        
        $this->assertCount(2, $account->statement->transactions);
        
        $transactions = $account->statement->transactions;
        $this->assertEquals(0.0, $transactions[0]->amount);
        $this->assertEquals(0.0, $transactions[1]->amount);
        $this->assertEquals(0.0, $account->balance);
    }

    /**
     * Test large amount parsing
     */
    public function testLargeAmountParsing(): void
    {
        $ofxContent = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
</SONRS>
</SIGNONMSGSRSV1>
<BANKMSGSRSV1>
<STMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<STMTRS>
<CURDEF>USD
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>1000000
<FITID>TX001
<NAME>One Million
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231016
<TRNAMT>99999.99
<FITID>TX002
<NAME>Large Decimal
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>5000000.50
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);
        
        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);
        
        $this->assertCount(2, $account->statement->transactions);
        
        $transactions = $account->statement->transactions;
        $this->assertEquals(1000000.0, $transactions[0]->amount, 'Large integer should parse correctly');
        $this->assertEquals(99999.99, $transactions[1]->amount, 'Large decimal should parse correctly');
        $this->assertEquals(5000000.50, $account->balance);
    }
}
