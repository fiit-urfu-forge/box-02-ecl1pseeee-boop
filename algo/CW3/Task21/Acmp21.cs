using System;
using System.Globalization;
using System.IO;

namespace CW3.Task21.Acmp;

/// <summary>
/// Задача 21. Трипростые числа — самодостаточный файл для отправки решения.
///
/// Целевая платформа: .NET 8. Скопировать всё содержимое файла
/// (включая using-и и namespace) в окно отправки решения.
///
/// Формат ввода (stdin):
///   n
/// где n (3 ≤ n ≤ 10000) — количество разрядов числа.
///
/// Формат вывода (stdout):
///   Количество n-значных 3-простых чисел по модулю 10^9 + 9.
///
/// Решение: динамика по парам последних цифр (a, b). Состояние dp[a, b] —
/// количество подходящих чисел текущей длины, оканчивающихся цифрами a, b.
/// Переход: к числу можно дописать цифру c, если число abc — трёхзначное простое.
/// </summary>
internal static class Acmp21
{
    private const long Mod = 1_000_000_009L;

    private static void Main()
    {
        var inv = CultureInfo.InvariantCulture;
        var input = Console.In.ReadToEnd().Trim();
        int n = int.Parse(input, inv);

        bool[] isPrime3 = BuildThreeDigitPrimes();

        long[,] dp = new long[10, 10];

        // База: трёхзначные простые числа (старший разряд от 1 до 9)
        for (int a = 1; a <= 9; a++)
            for (int b = 0; b <= 9; b++)
                for (int c = 0; c <= 9; c++)
                    if (isPrime3[a * 100 + b * 10 + c])
                        dp[b, c]++;

        // Дописываем цифры, пока длина не достигнет n
        for (int length = 3; length < n; length++)
        {
            long[,] next = new long[10, 10];
            // a — это «средняя» цифра нового окна abc; она должна быть ненулевой,
            // иначе окно не образует трёхзначного числа.
            for (int a = 1; a <= 9; a++)
                for (int b = 0; b <= 9; b++)
                {
                    long cur = dp[a, b];
                    if (cur == 0) continue;
                    for (int c = 0; c <= 9; c++)
                        if (isPrime3[a * 100 + b * 10 + c])
                            next[b, c] = (next[b, c] + cur) % Mod;
                }
            dp = next;
        }

        long total = 0;
        for (int a = 0; a <= 9; a++)
            for (int b = 0; b <= 9; b++)
                total = (total + dp[a, b]) % Mod;

        Console.WriteLine(total.ToString(inv));
    }

    private static bool[] BuildThreeDigitPrimes()
    {
        bool[] isPrime3 = new bool[1000];
        for (int x = 100; x <= 999; x++)
        {
            bool prime = true;
            for (int d = 2; d * d <= x; d++)
            {
                if (x % d == 0)
                {
                    prime = false;
                    break;
                }
            }
            isPrime3[x] = prime;
        }
        return isPrime3;
    }
}
