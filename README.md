# WorkingDirectory

Package WorkingDirectory terdiri dari:

  - Class ```IjorTengab\WorkingDirectory```

Requirement:
  - PHP > 5.4.0
  - Class ```IjorTengab\Logger\Log```

## IjorTengab\WorkingDirectory

WorkingDirectory adalah class yang menjadikan CWD (Current Working Directory)
sebagai sebuah object. Working directory pada object ini terpisah dengan
CWD milik PHP, sehingga dapat dibuat berbagai instance untuk direktori lainnya.

```php
$cwd = new WorkingDirectory;

echo getcwd(); // Output: /home/ijortengab
echo $cwd->getcwd(); // Output: /home/ijortengab

chdir('/var/www');
$cwd->chDir('secret');

echo getcwd(); // Output: /var/www
echo $cwd->getcwd(); // Output: /home/ijortengab/secret
```

Object ini nanti dapat dimasukkan file-file kedalamnya sebagai sebuah register.
Jika terjadi perpindahan working direktori ke tempat lain, maka file-file yang
telah ter-register tersebut akan turut otomatis pindah.

```php
$cwd = new WorkingDirectory('/home/ijortengab');
$cwd->addFile('my.html');
$cwd->addFile('my.txt');
$cwd->addFile('my.mp4');
$cwd->chDir('secret');
```
Contoh diatas akan memindahkan keseluruhan file (my.html, my.txt, my.mp4)
kedalam direktori secret (otomatis membuat direktori).

WorkingDirectory memudahkan untuk mengubah path menjadi absolute.

```php
$cwd = new WorkingDirectory('/home/ijortengab');

echo $my_html = $cwd->getAbsolutePath('test.html');
// Output: /home/ijortengab/test.html

echo $my_txt = $cwd->getAbsolutePath('doc/test.txt');
// Output: /home/ijortengab/doc/test.txt
```

Terdapat berbagai fungsi static:

```
// Memindahkan file secara massal.
WorkingDirectory::movingFiles($old_dir, $new_dir, $files);

// Membuat direktori dan memastikan dapat ditulis. Return boolean.
WorkingDirectory::prepareDirectory($dir);

// Mengecek apakah path relative alih-alih absolute.
WorkingDirectory::isRelativePath($path);
```
