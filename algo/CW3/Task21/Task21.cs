namespace CW3.Task21;

/// <summary>
/// Задача 21. Трипростые числа.
///
/// Число называется 3-простым, если любые три подряд идущие его цифры образуют
/// трёхзначное простое число. Нужно найти количество n-значных 3-простых чисел
/// по модулю 10^9 + 9 (3 &lt;= n &lt;= 10000).
///
/// Решение: динамика по парам последних цифр. Состояние dp[a][b] — количество
/// подходящих чисел текущей длины, оканчивающихся цифрами a, b. Переход: к числу,
/// оканчивающемуся на (a, b), можно дописать цифру c, если число abc — трёхзначное
/// простое. Тогда новое состояние — (b, c). На каждом шаге длина увеличивается на 1.
/// </summary>
public class Task21
{
    private const long Mod = 1_000_000_009L;

    /// <summary>
    /// Метод для запуска решения на примере из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        Console.WriteLine("################ Задача 21. Трипростые числа ################\n");
        Console.WriteLine($"n = 4 -> {Solution(4)} (ожидается 204)");
        Console.WriteLine("════════════════════════════════════════\n");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="n">Количество разрядов числа (3 &lt;= n &lt;= 10000).</param>
    /// <returns>Количество n-значных 3-простых чисел по модулю 10^9 + 9.</returns>
    public static long Solution(int n)
    {
        // Решето: isPrime3[x] = true, если x — трёхзначное простое число
        bool[] isPrime3 = BuildThreeDigitPrimes();

        // dp[a, b] — количество чисел текущей длины, оканчивающихся цифрами a, b
        long[,] dp = new long[10, 10];

        // База: все трёхзначные простые числа (первая цифра не равна нулю)
        for (int a = 1; a <= 9; a++)
            for (int b = 0; b <= 9; b++)
                for (int c = 0; c <= 9; c++)
                    if (isPrime3[a * 100 + b * 10 + c])
                        dp[b, c]++;

        // Дописываем цифры, пока длина не достигнет n
        for (int length = 3; length < n; length++)
        {
            long[,] next = new long[10, 10];
            // a — это «средняя» цифра нового окна, она должна быть ненулевой,
            // иначе окно abc не будет трёхзначным числом
            for (int a = 1; a <= 9; a++)
                for (int b = 0; b <= 9; b++)
                {
                    if (dp[a, b] == 0) continue;
                    for (int c = 0; c <= 9; c++)
                        if (isPrime3[a * 100 + b * 10 + c])
                            next[b, c] = (next[b, c] + dp[a, b]) % Mod;
                }
            dp = next;
        }

        // Ответ — сумма по всем возможным окончаниям
        long total = 0;
        for (int a = 0; a <= 9; a++)
            for (int b = 0; b <= 9; b++)
                total = (total + dp[a, b]) % Mod;

        return total;
    }

    /// <summary>
    /// Строит таблицу трёхзначных простых чисел (от 100 до 999).
    /// </summary>
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
