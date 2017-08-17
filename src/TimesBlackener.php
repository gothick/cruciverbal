<?php
namespace Gothick\Cruciverbal;

use Psr\Http\Message\StreamInterface;
use GuzzleHttp\Psr7\StreamDecoratorTrait;

require_once __DIR__ . '/../vendor/autoload.php';

class TimesBlackener implements StreamInterface
{
    use StreamDecoratorTrait;
    
    private $uncompressed_file_path;
    private $process;
    
    public function __construct(StreamInterface $stream) {
        // Bit complicated, but here we replace the incoming stream with a new one, based on a "blackened"
        // PDF file. The Times' pdfs are awful for printing, having about 70% grey text. It's especially
        // bad on my mono laser. Here we uncompress the file using QPDF, then we throw it through 
        $compressed_file_path = tempnam(sys_get_temp_dir(), 'cruciverbal');
        file_put_contents($compressed_file_path, $stream);
        
        // We save off the uncompressed file path so we can delete it when we're finished streaming,
        // i.e. on close().
        $this->uncompressed_file_path = tempnam(sys_get_temp_dir(), 'cruciverbal');
        // echo "Writing uncompressed PDF to $uncompressed_file_path\n";
        $command = "qpdf --qdf --object-streams=disable '$compressed_file_path' '{$this->uncompressed_file_path}'";
        // Uncompress the PDF
        exec($command, $output, $return_value);
        if ($return_value != 0) {
            // TODO: Perhaps we could just use the original stream if we fail rather than falling
            // over. On the other hand, QPDF seems perfectly reliable, and the Times' PDFs aren't
            // likely to vary that much.
            throw new \Exception("Got non-zero return value ($return_value) from QPDF exec()");
        }
        unlink($compressed_file_path);
        
        // Now the file is uncompressed, we darken it. We can use the results from sed as a stream.

        $descriptors = [
            ['file', $this->uncompressed_file_path, 'r'],
            ['pipe', 'w']
        ];

        $this->process = proc_open('sed s/0.298039215/0.0/g', $descriptors, $pipes);
        if (is_resource($this->process)) {
            $this->stream = \GuzzleHttp\Psr7\stream_for($pipes[1]);
        } else {
            throw new \Exception("Unexpected failure opening sed process");
        }
        // Close the original. We're not a wrapper; we're a replacement.
        $stream->close();
    }
    public function close() {
        // Tidy up after ourselves, and in particular don't leave temp files lying around.
        $this->stream->close();
        if (is_resource($this->process)) {
            proc_close($this->process);
        }
        if (file_exists($this->uncompressed_file_path)) {
            unlink($this->uncompressed_file_path);
        }
    }
    public function __destruct() {
        $this->close();
    }
}
