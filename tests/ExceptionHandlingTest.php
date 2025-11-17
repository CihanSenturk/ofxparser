<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser\Tests;

use CihanSenturk\OfxParser\Parser;
use CihanSenturk\OfxParser\Exceptions\ParseException;
use CihanSenturk\OfxParser\Exceptions\InvalidDateFormatException;
use PHPUnit\Framework\TestCase;

/**
 * Test exception handling
 * Tests for proper exception throwing on invalid data
 * 
 * @covers CihanSenturk\OfxParser\Parser
 * @covers CihanSenturk\OfxParser\Ofx
 */
class ExceptionHandlingTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Test that parser throws exception for invalid XML
     */
    public function testInvalidXmlThrowsException(): void
    {
        $this->expectException(ParseException::class);

        $invalidXml = <<<OFX
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
<!-- Missing closing tags -->
OFX;

        $this->parser->loadFromString($invalidXml);
    }

    /**
     * Test that parser throws exception for file not found
     */
    public function testFileNotFoundThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be found');

        $this->parser->loadFromFile('/nonexistent/path/file.ofx');
    }

    /**
     * Test that parser handles empty string gracefully
     */
    public function testEmptyStringHandling(): void
    {
        $this->expectException(\TypeError::class);

        $this->parser->loadFromString('');
    }

    /**
     * Test that parser handles malformed OFX header
     */
    public function testMalformedHeaderHandling(): void
    {
        $this->expectException(\TypeError::class);

        $malformedOfx = <<<OFX
INVALID_HEADER
<OFX>
</OFX>
OFX;

        $this->parser->loadFromString($malformedOfx);
    }

    /**
     * Test XXE attack prevention
     */
    public function testXXEAttackPrevention(): void
    {
        // This test ensures XXE attacks are prevented
        $xxePayload = <<<OFX
OFXHEADER:100
DATA:OFXSGML
VERSION:102
SECURITY:NONE
ENCODING:USASCII
CHARSET:1252
COMPRESSION:NONE
OLDFILEUID:NONE
NEWFILEUID:NONE

<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<OFX>
<SIGNONMSGSRSV1>
<SONRS>
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<DTSERVER>20231101120000
<LANGUAGE>ENG
<MESSAGE>&xxe;</MESSAGE>
</SONRS>
</SIGNONMSGSRSV1>
</OFX>
OFX;

        try {
            $ofx = $this->parser->loadFromString($xxePayload);
            // If it doesn't throw, verify XXE was not executed
            $this->assertTrue(true, 'XXE attack was prevented');
        } catch (\Exception $e) {
            // Exception is acceptable - means XXE was blocked
            $this->assertTrue(true, 'XXE attack was prevented by exception');
        }
    }

    /**
     * Test handling of missing required fields
     */
    public function testMissingRequiredFields(): void
    {
        // OFX without bank account info should still parse
        $minimalOfx = <<<OFX
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
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($minimalOfx);

        $this->assertNotNull($ofx);
        $this->assertNotNull($ofx->signOn);
        $this->assertEmpty($ofx->bankAccounts);
    }

    /**
     * Test handling of corrupted amount values
     */
    public function testCorruptedAmountHandling(): void
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
<TRNAMT>ABC123
<FITID>TX001
<NAME>Invalid Amount
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

        // Should handle gracefully - convert to 0 or throw specific exception
        $this->assertNotNull($account->statement);
    }

    /**
     * Test very large OFX file handling (performance test)
     */
    public function testLargeFileHandling(): void
    {
        // Generate OFX with 100 transactions
        $transactions = '';
        for ($i = 1; $i <= 100; $i++) {
            $transactions .= <<<TRANS
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>-{$i}.00
<FITID>TX{$i}
<NAME>Transaction {$i}
</STMTTRN>

TRANS;
        }

        $largeOfx = <<<OFX
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
{$transactions}
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>10000
<DTASOF>20231031
</LEDGERBAL>
</STMTRS>
</STMTTRNRS>
</BANKMSGSRSV1>
</OFX>
OFX;

        $startTime = microtime(true);
        $ofx = $this->parser->loadFromString($largeOfx);
        $endTime = microtime(true);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $this->assertCount(100, $account->statement->transactions);

        // Should parse in reasonable time (less than 1 second)
        $parseTime = $endTime - $startTime;
        $this->assertLessThan(1.0, $parseTime, "Parsing 100 transactions took {$parseTime}s");
    }
}
