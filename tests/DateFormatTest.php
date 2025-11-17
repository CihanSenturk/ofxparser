<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser\Tests;

use CihanSenturk\OfxParser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Test date format support improvements
 * Tests for MM/DD/YYYY, DD/MM/YYYY, and ISO 8601 date format support
 * 
 * @covers OfxParser\Ofx
 */
class DateFormatTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Test MM/DD/YYYY date format parsing (US format)
     */
    public function testMMDDYYYYDateFormat(): void
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
<DTSERVER>11/15/2023
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
<DTSTART>10/01/2023
<DTEND>10/31/2023
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>10/15/2023
<TRNAMT>-50.00
<FITID>TX001
<NAME>MM/DD/YYYY Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000.00
<DTASOF>10/31/2023
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
        
        // Verify the date was parsed correctly
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15', $transaction->date->format('Y-m-d'));
    }

    /**
     * Test DD/MM/YYYY date format parsing (European format)
     */
    public function testDDMMYYYYDateFormat(): void
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
<DTSERVER>15/11/2023
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
<CURDEF>EUR
<BANKACCTFROM>
<BANKID>123456
<ACCTID>9876543210
<ACCTTYPE>CHECKING
</BANKACCTFROM>
<BANKTRANLIST>
<DTSTART>01/10/2023
<DTEND>31/10/2023
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>25/10/2023
<TRNAMT>-75.00
<FITID>TX001
<NAME>DD/MM/YYYY Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>2000.00
<DTASOF>31/10/2023
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
        
        // Verify the date was parsed correctly (25/10/2023 = October 25, 2023)
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-25', $transaction->date->format('Y-m-d'));
    }

    /**
     * Test YYYYMMDD date format (original format - still supported)
     */
    public function testYYYYMMDDDateFormat(): void
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
<DTSERVER>20231115120000
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
<TRNAMT>-100.00
<FITID>TX001
<NAME>YYYYMMDD Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>3000.00
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
        
        // Verify the date was parsed correctly
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15', $transaction->date->format('Y-m-d'));
    }

    /**
     * Test YYYYMMDDHHMMSS date format with time
     */
    public function testYYYYMMDDHHMMSSDateFormat(): void
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
<DTSERVER>20231115143025
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
<DTSTART>20231001000000
<DTEND>20231031235959
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015143025
<TRNAMT>-125.50
<FITID>TX001
<NAME>YYYYMMDDHHMMSS Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>4000.00
<DTASOF>20231031235959
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
        
        // Verify the date and time were parsed correctly
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15 14:30:25', $transaction->date->format('Y-m-d H:i:s'));
    }
}
