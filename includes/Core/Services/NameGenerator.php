<?php
declare(strict_types=1);

namespace KaiMail\Core\Services;

/**
 * Name Generator Service
 * Generates meaningful email usernames (English/Vietnamese + numbers)
 * Format: firstnamelastname + 2-3 digit number (NO DOTS)
 */
final class NameGenerator
{
    private static array $englishFirstNames = [
        'james', 'john', 'robert', 'michael', 'william', 'david', 'richard', 'joseph',
        'thomas', 'charles', 'daniel', 'matthew', 'anthony', 'mark', 'donald', 'steven',
        'paul', 'andrew', 'joshua', 'kenneth', 'kevin', 'brian', 'george', 'edward',
        'ronald', 'timothy', 'jason', 'jeffrey', 'ryan', 'jacob', 'gary', 'nicholas',
        'eric', 'jonathan', 'stephen', 'larry', 'justin', 'scott', 'brandon', 'benjamin',
        'mary', 'patricia', 'jennifer', 'linda', 'barbara', 'elizabeth', 'susan', 'jessica',
        'sarah', 'karen', 'nancy', 'lisa', 'betty', 'margaret', 'sandra', 'ashley',
        'kimberly', 'emily', 'donna', 'michelle', 'dorothy', 'carol', 'amanda', 'melissa',
        'deborah', 'stephanie', 'rebecca', 'sharon', 'laura', 'cynthia', 'kathleen', 'amy',
        'angela', 'shirley', 'anna', 'brenda', 'pamela', 'emma', 'nicole', 'helen'
    ];

    private static array $englishLastNames = [
        'smith', 'johnson', 'williams', 'brown', 'jones', 'garcia', 'miller', 'davis',
        'rodriguez', 'martinez', 'hernandez', 'lopez', 'gonzalez', 'wilson', 'anderson',
        'thomas', 'taylor', 'moore', 'jackson', 'martin', 'lee', 'perez', 'thompson',
        'white', 'harris', 'sanchez', 'clark', 'ramirez', 'lewis', 'robinson', 'walker',
        'young', 'allen', 'king', 'wright', 'scott', 'torres', 'nguyen', 'hill',
        'flores', 'green', 'adams', 'nelson', 'baker', 'hall', 'rivera', 'campbell',
        'mitchell', 'carter', 'roberts', 'gomez', 'phillips', 'evans', 'turner', 'diaz',
        'parker', 'cruz', 'edwards', 'collins', 'reyes', 'stewart', 'morris', 'morales', 'murphy'
    ];

    private static array $vietnameseLastNames = [
        'nguyen', 'tran', 'le', 'pham', 'hoang', 'phan', 'vu', 'vo', 'dang', 'bui',
        'do', 'ho', 'ngo', 'duong', 'ly', 'dinh', 'mai', 'truong', 'cao', 'trinh',
        'ta', 'lam', 'huynh', 'luong', 'ha', 'tong', 'quach', 'chu', 'bach', 'hien',
        'huy', 'tam', 'tri', 'tai', 'tien', 'tinh', 'tuan', 'tuyen', 'tung', 'tiang',
        'tuong', 'thai', 'vong', 'la', 'lieu', 'vinh', 'nguong', 'phung', 'nhat', 'dao',
        'uong', 'phi', 'ong', 'nong', 'khuat', 'nghiem', 'ton', 'chau', 'dieu', 'mac',
        'hua', 'vuong', 'kiem', 'binh', 'trang', 'huu', 'thach', 'lu', 'nguu', 'khong',
        'the', 'nguy', 'vien', 'on', 'thoi', 'nham', 'trieu', 'buu', 'man', 'giang',
        'kha', 'sam', 'bien', 'tranh', 'than', 'khuu', 'to', 'kim'
    ];

    private static array $vietnameseFirstNames = [
        'an', 'anh', 'bao', 'binh', 'cuong', 'dung', 'duc', 'hai', 'hieu', 'hoa',
        'hoang', 'hung', 'huong', 'huy', 'khanh', 'khoa', 'lan', 'linh', 'long', 'mai',
        'minh', 'nam', 'nga', 'nhan', 'nhung', 'phuong', 'quan', 'quang', 'quynh', 'son',
        'tam', 'thanh', 'thao', 'thi', 'thuy', 'tien', 'trinh', 'trung', 'tu', 'tuan',
        'tuyet', 'van', 'viet', 'vu', 'xuan', 'yen', 'bach', 'duy', 'kien', 'khai',
        'nhat', 'ngoc', 'phuc', 'loc', 'phat', 'thang', 'thinh', 'khang', 'tuong', 'chien',
        'dat', 'nghia', 'triet', 'uong', 'vy', 'diep', 'nguyet', 'que', 'tram', 'uyen',
        'diu', 'trang', 'hanh', 'thuc', 'quyen', 'my', 'kieu', 'oanh', 'toan', 'thuan',
        'dai', 'lam', 'vinh', 'duong', 'phong', 'sang', 'manh', 'loi', 'nghiep', 'bang',
        'khoi', 'huan'
    ];

    /**
     * Generate random meaningful username
     * Format: firstnamelastname + random number (NO DOTS)
     */
    public static function generateUsername(string $type = 'random'): string
    {
        if ($type === 'vn') {
            $useEnglish = false;
        } elseif ($type === 'en') {
            $useEnglish = true;
        } else {
            $useEnglish = mt_rand(0, 1) === 1;
        }

        if ($useEnglish) {
            $firstName = self::$englishFirstNames[array_rand(self::$englishFirstNames)];
            $lastName = self::$englishLastNames[array_rand(self::$englishLastNames)];
        } else {
            $firstName = self::$vietnameseFirstNames[array_rand(self::$vietnameseFirstNames)];
            $lastName = self::$vietnameseLastNames[array_rand(self::$vietnameseLastNames)];
        }

        $number = mt_rand(10, 999);
        return $firstName . $lastName . $number;
    }

    /**
     * Get name type from generated username
     */
    public static function getNameType(string $type = 'random'): string
    {
        if ($type === 'vn') {
            return 'vn';
        }
        if ($type === 'en') {
            return 'en';
        }
        return mt_rand(0, 1) === 1 ? 'en' : 'vn';
    }
}
