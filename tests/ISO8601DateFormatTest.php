<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser\Tests;

use CihanSenturk\OfxParser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Test ISO 8601 date format support
 * 
 * @covers OfxParser\Ofx
 */
class ISO8601DateFormatTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Test ISO 8601 YYYY-MM-DD format
     */
    public function testISO8601BasicFormat(): void
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
<DTSERVER>2023-11-15
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
<DTSTART>2023-10-01
<DTEND>2023-10-31
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>2023-10-15
<TRNAMT>-100.00
<FITID>TX001
<NAME>ISO 8601 Basic Format Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>1000.00
<DTASOF>2023-10-31
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
        
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15', $transaction->date->format('Y-m-d'));
    }

    /**
     * Test ISO 8601 YYYY-MM-DDTHH:MM:SS format
     */
    public function testISO8601WithTimeFormat(): void
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
<DTSERVER>2023-11-15T14:30:25
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
<DTSTART>2023-10-01T00:00:00
<DTEND>2023-10-31T23:59:59
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>2023-10-15T14:30:25
<TRNAMT>-150.75
<FITID>TX001
<NAME>ISO 8601 With Time Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>2000.00
<DTASOF>2023-10-31T23:59:59
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
        
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15 14:30:25', $transaction->date->format('Y-m-d H:i:s'));
    }

    /**
     * Test ISO 8601 with timezone offset (YYYY-MM-DDTHH:MM:SS±HH:MM)
     */
    public function testISO8601WithTimezoneFormat(): void
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
<DTSERVER>2023-11-15T14:30:25+05:00
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
<DTSTART>2023-10-01T00:00:00+05:00
<DTEND>2023-10-31T23:59:59+05:00
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>2023-10-15T14:30:25+05:00
<TRNAMT>-200.00
<FITID>TX001
<NAME>ISO 8601 With Timezone Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>3000.00
<DTASOF>2023-10-31T23:59:59+05:00
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
        
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals('14:30:25', $transaction->date->format('H:i:s'));
    }

    /**
     * Test ISO 8601 UTC format (YYYY-MM-DDTHH:MM:SSZ)
     */
    public function testISO8601UTCFormat(): void
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
<DTSERVER>2023-11-15T14:30:25Z
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
<DTSTART>2023-10-01T00:00:00Z
<DTEND>2023-10-31T23:59:59Z
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>2023-10-15T14:30:25Z
<TRNAMT>-250.50
<FITID>TX001
<NAME>ISO 8601 UTC Format Test
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>4000.00
<DTASOF>2023-10-31T23:59:59Z
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
        
        $this->assertInstanceOf(\DateTime::class, $transaction->date);
        $this->assertEquals('2023-10-15', $transaction->date->format('Y-m-d'));
        $this->assertEquals('14:30:25', $transaction->date->format('H:i:s'));
    }
}
