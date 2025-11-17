<?php

declare(strict_types=1);

namespace CihanSenturk\OfxParser\Tests;

use CihanSenturk\OfxParser\Parser;
use PHPUnit\Framework\TestCase;

/**
 * Test credit card statement parsing
 * Tests for credit card accounts (CREDITCARDMSGSRSV1)
 * 
 * @covers CihanSenturk\OfxParser\Parser
 * @covers CihanSenturk\OfxParser\Ofx
 */
class CreditCardTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * Test basic credit card statement parsing
     */
    public function testCreditCardStatementParsing(): void
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
<CREDITCARDMSGSRSV1>
<CCSTMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<CCSTMTRS>
<CURDEF>USD
<CCACCTFROM>
<ACCTID>1234567891234567
</CCACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>-75.50
<FITID>CC001
<NAME>Amazon Purchase
</STMTTRN>
<STMTTRN>
<TRNTYPE>PAYMENT
<DTPOSTED>20231020
<TRNAMT>500.00
<FITID>CC002
<NAME>Payment - Thank You
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>-1250.75
<DTASOF>20231031
</LEDGERBAL>
</CCSTMTRS>
</CCSTMTTRNRS>
</CREDITCARDMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $this->assertEquals('1234567891234567', (string)$account->accountNumber);
        $this->assertEquals(-1250.75, $account->balance);
        $this->assertCount(2, $account->statement->transactions);

        $transactions = $account->statement->transactions;
        $this->assertEquals(-75.50, $transactions[0]->amount);
        $this->assertEquals(500.00, $transactions[1]->amount);
    }

    /**
     * Test credit card with multiple purchases
     */
    public function testCreditCardMultiplePurchases(): void
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
<CREDITCARDMSGSRSV1>
<CCSTMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<CCSTMTRS>
<CURDEF>USD
<CCACCTFROM>
<ACCTID>4111111111111111
</CCACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>POS
<DTPOSTED>20231005
<TRNAMT>-45.99
<FITID>CC001
<NAME>Grocery Store
</STMTTRN>
<STMTTRN>
<TRNTYPE>POS
<DTPOSTED>20231010
<TRNAMT>-120.00
<FITID>CC002
<NAME>Gas Station
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>-299.99
<FITID>CC003
<NAME>Electronics Store
</STMTTRN>
<STMTTRN>
<TRNTYPE>FEE
<DTPOSTED>20231025
<TRNAMT>-25.00
<FITID>CC004
<NAME>Late Payment Fee
</STMTTRN>
<STMTTRN>
<TRNTYPE>INT
<DTPOSTED>20231031
<TRNAMT>-15.50
<FITID>CC005
<NAME>Interest Charge
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>-2506.48
<DTASOF>20231031
</LEDGERBAL>
</CCSTMTRS>
</CCSTMTTRNRS>
</CREDITCARDMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $this->assertCount(5, $account->statement->transactions);

        // Calculate total charges
        $totalCharges = 0;
        foreach ($account->statement->transactions as $transaction) {
            $totalCharges += $transaction->amount;
        }

        $expectedTotal = -45.99 + -120.00 + -299.99 + -25.00 + -15.50;
        $this->assertEquals($expectedTotal, $totalCharges);
    }

    /**
     * Test multiple credit card statements in same file
     */
    public function testMultipleCreditCardStatements(): void
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
<CREDITCARDMSGSRSV1>
<CCSTMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<CCSTMTRS>
<CURDEF>USD
<CCACCTFROM>
<ACCTID>1111222233334444
</CCACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231015
<TRNAMT>-100.00
<FITID>CARD1001
<NAME>Card 1 Purchase
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>-100.00
<DTASOF>20231031
</LEDGERBAL>
</CCSTMTRS>
</CCSTMTTRNRS>
<CCSTMTTRNRS>
<TRNUID>2
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<CCSTMTRS>
<CURDEF>USD
<CCACCTFROM>
<ACCTID>5555666677778888
</CCACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20231020
<TRNAMT>-250.00
<FITID>CARD2001
<NAME>Card 2 Purchase
</STMTTRN>
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>-250.00
<DTASOF>20231031
</LEDGERBAL>
</CCSTMTRS>
</CCSTMTTRNRS>
</CREDITCARDMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        // Should have 2 credit card accounts
        $this->assertCount(2, $ofx->bankAccounts);

        $card1 = $ofx->bankAccounts[0];
        $card2 = $ofx->bankAccounts[1];

        // Verify first credit card
        $this->assertEquals('1111222233334444', (string)$card1->accountNumber);
        $this->assertEquals(-100.00, $card1->balance);
        $this->assertCount(1, $card1->statement->transactions);
        $this->assertEquals('Card 1 Purchase', $card1->statement->transactions[0]->name);

        // Verify second credit card
        $this->assertEquals('5555666677778888', (string)$card2->accountNumber);
        $this->assertEquals(-250.00, $card2->balance);
        $this->assertCount(1, $card2->statement->transactions);
        $this->assertEquals('Card 2 Purchase', $card2->statement->transactions[0]->name);
    }

    /**
     * Test credit card account with no transactions (zero balance)
     */
    public function testCreditCardZeroBalance(): void
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
<CREDITCARDMSGSRSV1>
<CCSTMTTRNRS>
<TRNUID>1
<STATUS>
<CODE>0
<SEVERITY>INFO
</STATUS>
<CCSTMTRS>
<CURDEF>USD
<CCACCTFROM>
<ACCTID>1234567891234567
</CCACCTFROM>
<BANKTRANLIST>
<DTSTART>20231001
<DTEND>20231031
</BANKTRANLIST>
<LEDGERBAL>
<BALAMT>0.00
<DTASOF>20231031
</LEDGERBAL>
</CCSTMTRS>
</CCSTMTTRNRS>
</CREDITCARDMSGSRSV1>
</OFX>
OFX;

        $ofx = $this->parser->loadFromString($ofxContent);

        $this->assertNotEmpty($ofx->bankAccounts);
        $account = reset($ofx->bankAccounts);

        $this->assertEquals(0.00, $account->balance);
        $this->assertEmpty($account->statement->transactions);
    }
}
