<?php

/**
 * @see       https://github.com/laminas/laminas-mail for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mail/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mail/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mail\Storage;

use Laminas\Mail\Storage;
use Laminas\Mail\Storage\Writable;
use PHPUnit\Framework\TestCase;

/**
 * @group      Laminas_Mail
 */
class MaildirWritableTest extends TestCase
{
    protected $params;
    protected $tmpdir;
    protected $subdirs = ['.', '.subfolder', '.subfolder.test'];

    public function setUp()
    {
        if (\strtoupper(\substr(PHP_OS, 0, 3)) == 'WIN') {
            $this->markTestSkipped('This test does not work on Windows');
            return;
        }

        $originalMaildir = __DIR__ . '/../_files/test.maildir/';

        if ($this->tmpdir == null) {
            if (getenv('TESTS_LAMINAS_MAIL_TEMPDIR') != null) {
                $this->tmpdir = getenv('TESTS_LAMINAS_MAIL_TEMPDIR');
            } else {
                $this->tmpdir = __DIR__ . '/../_files/test.tmp/';
            }
            if (! file_exists($this->tmpdir)) {
                mkdir($this->tmpdir);
            }
            $count = 0;
            $dh = opendir($this->tmpdir);
            while (readdir($dh) !== false) {
                ++$count;
            }
            closedir($dh);

            if ($count != 2) {
                $this->markTestSkipped('Are you sure your tmp dir is a valid empty dir?');
                return;
            }
        }

        if (! \file_exists($originalMaildir . 'maildirsize') && \class_exists('PharData')) {
            try {
                $phar = new \PharData($originalMaildir . 'maildir.tar');
                $phar->extractTo($originalMaildir);
            } catch (\Exception $e) {
                // intentionally empty catch block
            }
        }

        if (! \file_exists($originalMaildir . 'maildirsize')) {
            $this->markTestSkipped('You have to unpack maildir.tar in '
            . 'Laminas/Mail/_files/test.maildir/ directory to run the maildir tests');
            return;
        }

        $this->params = [];
        $this->params['dirname'] = $this->tmpdir;

        foreach ($this->subdirs as $dir) {
            if ($dir != '.') {
                mkdir($this->tmpdir . $dir);
            }
            foreach (['cur', 'new'] as $subdir) {
                if (! file_exists($originalMaildir . $dir . '/' . $subdir)) {
                    continue;
                }
                mkdir($this->tmpdir . $dir . '/' . $subdir);
                $dh = opendir($originalMaildir . $dir . '/' . $subdir);
                while (($entry = readdir($dh)) !== false) {
                    $entry = $dir . '/' . $subdir . '/' . $entry;
                    if (! is_file($originalMaildir . $entry)) {
                        continue;
                    }
                    copy($originalMaildir . $entry, $this->tmpdir . $entry);
                }
                closedir($dh);
            }
            copy($originalMaildir . 'maildirsize', $this->tmpdir . 'maildirsize');
        }
    }

    public function tearDown()
    {
        foreach (array_reverse($this->subdirs) as $dir) {
            if (! file_exists($this->tmpdir . $dir)) {
                continue;
            }
            foreach (['cur', 'new', 'tmp'] as $subdir) {
                if (! file_exists($this->tmpdir . $dir . '/' . $subdir)) {
                    continue;
                }
                $dh = opendir($this->tmpdir . $dir . '/' . $subdir);
                while (($entry = readdir($dh)) !== false) {
                    $entry = $this->tmpdir . $dir . '/' . $subdir . '/' . $entry;
                    if (! is_file($entry)) {
                        continue;
                    }
                    unlink($entry);
                }
                closedir($dh);
                rmdir($this->tmpdir . $dir . '/' . $subdir);
            }
            if ($dir != '.') {
                rmdir($this->tmpdir . $dir);
            }
        }
        @unlink($this->tmpdir . 'maildirsize');
    }

    public function testCreateFolder()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->createFolder('subfolder.test1');
        $mail->createFolder('test2', 'INBOX.subfolder');
        $mail->createFolder('test3', $mail->getFolders()->subfolder);
        $mail->createFolder('foo.bar');

        $mail->selectFolder($mail->getFolders()->subfolder->test1);
        $mail->selectFolder($mail->getFolders()->subfolder->test2);
        $mail->selectFolder($mail->getFolders()->subfolder->test3);
        $mail->selectFolder($mail->getFolders()->foo->bar);

        // to tear down
        $this->subdirs[] = '.subfolder.test1';
        $this->subdirs[] = '.subfolder.test2';
        $this->subdirs[] = '.subfolder.test3';
        $this->subdirs[] = '.foo';
        $this->subdirs[] = '.foo.bar';
    }

    public function testCreateFolderEmptyPart()
    {
        $mail = new Writable\Maildir($this->params);
        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('invalid name - folder parts may not be empty');
        $mail->createFolder('foo..bar');
    }

    public function testCreateFolderSlash()
    {
        $mail = new Writable\Maildir($this->params);
        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('invalid name - no directory separator allowed in folder name');
        $mail->createFolder('foo/bar');
    }

    public function testCreateFolderDirectorySeparator()
    {
        $mail = new Writable\Maildir($this->params);
        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('invalid name - no directory separator allowed in folder name');
        $mail->createFolder('foo' . DIRECTORY_SEPARATOR . 'bar');
    }

    public function testCreateFolderExistingDir()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        unset($mail->getFolders()->subfolder->test);

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('error while creating new folder, may be created incompletely');
        $mail->createFolder('subfolder.test');
    }

    public function testCreateExistingFolder()
    {
        $mail = new Writable\Maildir($this->params);

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('folder already exists');
        $mail->createFolder('subfolder.test');
    }

    public function testRemoveFolderName()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->removeFolder('INBOX.subfolder.test');

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('no subfolder named test');
        $mail->selectFolder($mail->getFolders()->subfolder->test);
    }

    public function testRemoveFolderInstance()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->removeFolder($mail->getFolders()->subfolder->test);

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('no subfolder named test');
        $mail->selectFolder($mail->getFolders()->subfolder->test);
    }

    public function testRemoveFolderWithChildren()
    {
        $mail = new Writable\Maildir($this->params);

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('delete children first');
        $mail->removeFolder($mail->getFolders()->subfolder);
    }

    public function testRemoveSelectedFolder()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->selectFolder('subfolder.test');

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $mail->removeFolder('subfolder.test');
    }

    public function testRemoveInvalidFolder()
    {
        $mail = new Writable\Maildir($this->params);

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('no subfolder named thisFolderDoestNotExist');
        $mail->removeFolder('thisFolderDoestNotExist');
    }

    public function testRenameFolder()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);

        $mail->renameFolder('INBOX.subfolder', 'INBOX.foo');
        $mail->renameFolder($mail->getFolders()->foo, 'subfolder');

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('wont rename INBOX');
        $mail->renameFolder('INBOX', 'foo');
    }

    public function testRenameSelectedFolder()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->selectFolder('subfolder.test');

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $mail->renameFolder('subfolder.test', 'foo');
    }

    public function testRenameToChild()
    {
        $mail = new Writable\Maildir($this->params);

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('new folder cannot be a child of old folder');
        $mail->renameFolder('subfolder.test', 'subfolder.test.foo');
    }

    public function testAppend()
    {
        $mail = new Writable\Maildir($this->params);
        $count = $mail->countMessages();

        $message = '';
        $message .= "From: me@example.org\r\n";
        $message .= "To: you@example.org\r\n";
        $message .= "Subject: append test\r\n";
        $message .= "\r\n";
        $message .= "This is a test\r\n";
        $mail->appendMessage($message);

        $this->assertEquals($count + 1, $mail->countMessages());
        $this->assertEquals($mail->getMessage($count + 1)->subject, 'append test');
    }

    public function testCopy()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);

        $mail->selectFolder('subfolder.test');
        $count = $mail->countMessages();
        $mail->selectFolder('INBOX');
        $message = $mail->getMessage(1);

        $mail->copyMessage(1, 'subfolder.test');
        $mail->selectFolder('subfolder.test');
        $this->assertEquals($count + 1, $mail->countMessages());
        $this->assertEquals($mail->getMessage($count + 1)->subject, $message->subject);
        $this->assertEquals($mail->getMessage($count + 1)->from, $message->from);
        $this->assertEquals($mail->getMessage($count + 1)->to, $message->to);

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $mail->copyMessage(1, 'justARandomFolder');
    }

    public function testSetFlags()
    {
        $mail = new Writable\Maildir($this->params);

        $mail->setFlags(1, [Storage::FLAG_SEEN]);
        $message = $mail->getMessage(1);
        $this->assertTrue($message->hasFlag(Storage::FLAG_SEEN));
        $this->assertFalse($message->hasFlag(Storage::FLAG_FLAGGED));

        $mail->setFlags(1, [Storage::FLAG_SEEN, Storage::FLAG_FLAGGED]);
        $message = $mail->getMessage(1);
        $this->assertTrue($message->hasFlag(Storage::FLAG_SEEN));
        $this->assertTrue($message->hasFlag(Storage::FLAG_FLAGGED));

        $mail->setFlags(1, [Storage::FLAG_FLAGGED]);
        $message = $mail->getMessage(1);
        $this->assertFalse($message->hasFlag(Storage::FLAG_SEEN));
        $this->assertTrue($message->hasFlag(Storage::FLAG_FLAGGED));

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('recent flag may not be set');
        $mail->setFlags(1, [Storage::FLAG_RECENT]);
    }

    public function testSetFlagsRemovedFile()
    {
        $mail = new Writable\Maildir($this->params);
        unlink($this->params['dirname'] . 'cur/1000000000.P1.example.org:2,S');

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
    }

    public function testRemove()
    {
        $mail = new Writable\Maildir($this->params);
        $count = $mail->countMessages();

        $mail->removeMessage(1);
        $this->assertEquals($mail->countMessages(), --$count);

        unset($mail[2]);
        $this->assertEquals($mail->countMessages(), --$count);
    }

    public function testRemoveRemovedFile()
    {
        $mail = new Writable\Maildir($this->params);
        unlink($this->params['dirname'] . 'cur/1000000000.P1.example.org:2,S');

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('cannot remove message');
        $mail->removeMessage(1);
    }

    public function testCheckQuota()
    {
        $mail = new Writable\Maildir($this->params);
        $this->assertFalse($mail->checkQuota());
    }

    public function testCheckQuotaDetailed()
    {
        $mail = new Writable\Maildir($this->params);
        $quotaResult = [
            'size'  => 2129,
            'count' => 5,
            'quota' => [
                    'count' => 10,
                    'L'     => 1,
                    'size'  => 3000
                ],
            'over_quota' => false
        ];
        $this->assertEquals($quotaResult, $mail->checkQuota(true));
    }

    public function testSetQuota()
    {
        $mail = new Writable\Maildir($this->params);
        $this->assertNull($mail->getQuota());

        $mail->setQuota(true);
        $this->assertTrue($mail->getQuota());

        $mail->setQuota(false);
        $this->assertFalse($mail->getQuota());

        $mail->setQuota(['size' => 100, 'count' => 2, 'X' => 0]);
        $this->assertEquals($mail->getQuota(), ['size' => 100, 'count' => 2, 'X' => 0]);
        $this->assertEquals($mail->getQuota(true), ['size' => 3000, 'L' => 1, 'count' => 10]);

        $quotaResult = [
            'size'  => 2129,
            'count' => 5,
            'quota' => [
                    'size'  => 100,
                    'count' => 2,
                    'X'     => 0
                ],
            'over_quota' => true
        ];
        $this->assertEquals($quotaResult, $mail->checkQuota(true, true));
        $this->assertEquals(['size' => 100, 'count' => 2, 'X' => 0], $mail->getQuota(true));
    }

    public function testMissingMaildirsize()
    {
        $mail = new Writable\Maildir($this->params);
        $this->assertEquals($mail->getQuota(true), ['size' => 3000, 'L' => 1, 'count' => 10]);

        unlink($this->tmpdir . 'maildirsize');

        $this->assertNull($mail->getQuota());

        $this->expectException('Laminas\Mail\Storage\Exception\RuntimeException');
        $this->expectExceptionMessage('cannot open maildirsize');
        $mail->getQuota(true);
    }

    public function testMissingMaildirsizeWithFixedQuota()
    {
        $mail = new Writable\Maildir($this->params);
        unlink($this->tmpdir . 'maildirsize');
        $mail->setQuota(['size' => 100, 'count' => 2, 'X' => 0]);

        $quotaResult = [
            'size'  => 2129,
            'count' => 5,
            'quota' => [
                    'size'  => 100,
                    'count' => 2,
                    'X'     => 0
                ],
            'over_quota' => true
        ];
        $this->assertEquals($mail->checkQuota(true), $quotaResult);

        $this->assertEquals($mail->getQuota(true), $quotaResult['quota']);
    }

    public function testAppendMessage()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->setQuota(['size' => 3000, 'count' => 6, 'X' => 0]);
        $this->assertFalse($mail->checkQuota(false, true));
        $mail->appendMessage("Subject: test\r\n\r\n");
        $quotaResult = [
            'size'  => 2613,
            'count' => 7,
            'quota' => [
                    'size'  => 3000,
                    'count' => 6,
                    'X'     => 0
                ],
            'over_quota' => true
        ];
        $this->assertEquals($mail->checkQuota(true), $quotaResult);

        $mail->setQuota(false);
        $this->assertTrue($mail->checkQuota());

        $mail->appendMessage("Subject: test\r\n\r\n");

        $mail->setQuota(true);
        $this->assertTrue($mail->checkQuota());

        $this->expectException('Laminas\Mail\Storage\Exception\InvalidArgumentException');
        $mail->appendMessage("Subject: test\r\n\r\n");
    }

    public function testRemoveMessage()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->setQuota(['size' => 3000, 'count' => 5, 'X' => 0]);
        $this->assertTrue($mail->checkQuota(false, true));

        $mail->removeMessage(1);
        $this->assertFalse($mail->checkQuota());
    }

    public function testCopyMessage()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $mail->setQuota(['size' => 3000, 'count' => 6, 'X' => 0]);
        $this->assertFalse($mail->checkQuota(false, true));
        $mail->copyMessage(1, 'subfolder');
        $quotaResult = [
            'size'  => 2993,
            'count' => 7,
            'quota' => [
                    'size'  => 3000,
                    'count' => 6,
                    'X'     => 0
                ],
            'over_quota' => true
        ];
        $this->assertEquals($mail->checkQuota(true), $quotaResult);
    }

    public function testAppendStream()
    {
        $mail = new Writable\Maildir($this->params);
        $fh = fopen('php://memory', 'rw');
        fputs($fh, "Subject: test\r\n\r\n");
        fseek($fh, 0);
        $mail->appendMessage($fh);
        fclose($fh);

        $this->assertEquals($mail->getMessage($mail->countMessages())->subject, 'test');
    }

    public function testMove()
    {
        $this->markTestIncomplete("Fail");
        $mail = new Writable\Maildir($this->params);
        $target = $mail->getFolders()->subfolder->test;
        $mail->selectFolder($target);
        $toCount = $mail->countMessages();
        $mail->selectFolder('INBOX');
        $fromCount = $mail->countMessages();
        $mail->moveMessage(1, $target);


        $this->assertEquals($fromCount - 1, $mail->countMessages());
        $mail->selectFolder($target);
        $this->assertEquals($toCount + 1, $mail->countMessages());
    }

    public function testInitExisting()
    {
        // this should be a noop
        Writable\Maildir::initMaildir($this->params['dirname']);
        $mail = new Writable\Maildir($this->params);
        $this->assertEquals($mail->countMessages(), 5);
    }

    public function testInit()
    {
        $this->tearDown();

        // should fail now
        $e = null;
        try {
            $mail = new Writable\Maildir($this->params);
            $this->fail('empty maildir should not be accepted');
        } catch (\Exception $e) {
        }

        Writable\Maildir::initMaildir($this->params['dirname']);
        $mail = new Writable\Maildir($this->params);
        $this->assertEquals($mail->countMessages(), 0);
    }

    public function testCreate()
    {
        $this->tearDown();

        // should fail now
        $e = null;
        try {
            $mail = new Writable\Maildir($this->params);
            $this->fail('empty maildir should not be accepted');
        } catch (\Exception $e) {
        }

        $this->params['create'] = true;
        $mail = new Writable\Maildir($this->params);
        $this->assertEquals($mail->countMessages(), 0);
    }
}
