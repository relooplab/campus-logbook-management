// Urutan penting: CSS dasar PDF.js dulu (posisi .page/.textLayer/.canvas),
// lalu timpaan dari react-pdf-highlighter-plus (file pdf_viewer.css miliknya
// memang hanya berisi override). Tanpa ini teks menumpuk di bawah halaman
// dan tidak bisa diseleksi — persis seperti App.css example-app:
//   @import "pdfjs-dist/web/pdf_viewer.css";
import 'pdfjs-dist/web/pdf_viewer.css';
import 'react-pdf-highlighter-plus/style/style.css';
import './components/PdfViewerApp.jsx';
