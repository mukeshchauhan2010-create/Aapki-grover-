<?php
declare(strict_types=1);
/**
 * MiniPDF — a tiny, dependency-free PDF writer for simple text documents
 * (invoices). Single page, Helvetica, supports lines of text, simple
 * horizontal rules, and right-aligned amounts. Not a full PDF engine — just
 * enough for a clean A4 invoice without any external library/composer.
 *
 * Coordinates: origin top-left, units = points (72 pt = 1 inch). A4 = 595x842.
 */
final class MiniPDF {
    private float $w = 595.28, $h = 841.89;   // A4 portrait
    private array $ops = [];
    private float $y;

    public function __construct() { $this->y = $this->h - 48; }

    private static function esc(string $s): string {
        // Keep to Latin-1 printable; replace rupee sign and unknowns.
        $s = str_replace(['₹','–','—','’','“','”','•'], ['Rs. ','-','-',"'",'"','"','*'], $s);
        $s = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
        return str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $s);
    }

    public function text(float $x, string $s, float $size = 10, bool $bold = false): void {
        $f = $bold ? 'F2' : 'F1';
        $this->ops[] = sprintf("BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET", $f, $size, $x, $this->y, self::esc($s));
    }
    /** Text + right-aligned amount on the same line, then advance. */
    public function row(string $left, string $right, float $size = 10, bool $bold = false): void {
        $f = $bold ? 'F2' : 'F1';
        $this->ops[] = sprintf("BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET", $f, $size, 48.0, $this->y, self::esc($left));
        // right aligned near right margin (~547)
        $approx = strlen($right) * $size * 0.5;
        $this->ops[] = sprintf("BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET", $f, $size, 547.0 - $approx, $this->y, self::esc($right));
        $this->y -= $size + 6;
    }
    public function line(string $s = '', float $size = 10, bool $bold = false): void {
        if ($s !== '') $this->text(48, $s, $size, $bold);
        $this->y -= $size + 6;
    }
    public function gap(float $pts = 8): void { $this->y -= $pts; }
    public function hr(): void {
        $this->ops[] = sprintf("0.8 w 48 %.2f m 547 %.2f l S", $this->y, $this->y);
        $this->y -= 10;
    }

    public function output(string $filename = 'document.pdf', bool $download = true): void {
        $content = implode("\n", $this->ops);
        $objs = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ".sprintf('%.2f %.2f',$this->w,$this->h)."] "
                 . "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>";
        $objs[4] = "<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream";
        $objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objs[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[$i] = strlen($pdf);
            $pdf .= $i." 0 obj\n".$o."\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $n = count($objs) + 1;
        $pdf .= "xref\n0 ".$n."\n0000000000 65535 f \n";
        for ($i = 1; $i < $n; $i++) { $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]); }
        $pdf .= "trailer\n<< /Size ".$n." /Root 1 0 R >>\nstartxref\n".$xrefPos."\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: '.($download ? 'attachment' : 'inline').'; filename="'.$filename.'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
    }
}
