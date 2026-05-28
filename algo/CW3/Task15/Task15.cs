namespace CW3.Task15;

/// <summary>
/// Задача 15. Наибольшая общая подстрока.
///
/// Даны две строки. Нужно найти их общую подстроку наибольшей длины, а если таких
/// несколько — вывести лексикографически наименьшую.
///
/// Решение состоит из двух шагов:
/// 1. Бинарный поиск по длине ответа. Для фиксированной длины L все подстроки первой
///    строки складываются в хеш-множество (полиномиальное хеширование), затем
///    проверяется, встречается ли хоть одна подстрока второй строки той же длины.
///    Хеш двойной (два модуля) — это практически исключает коллизии.
/// 2. Когда найдена максимальная длина maxLen, среди всех общих подстрок этой длины
///    выбирается лексикографически наименьшая.
/// </summary>
public class Task15
{
    // Параметры полиномиального хеширования
    private const long Mod1 = 1_000_000_007L;
    private const long Mod2 = 998_244_353L;
    private const long Base1 = 131;
    private const long Base2 = 137;

    /// <summary>
    /// Метод для запуска решения на примере из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        string a = "ubrashvabracadabra";
        string b = "calamburashabratha";

        Console.WriteLine("Задача 15. Наибольшая общая подстрока.");
        Console.WriteLine($"Строка 1: {a}");
        Console.WriteLine($"Строка 2: {b}");
        Console.WriteLine($"Результат: \"{Solution(a, b)}\"");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="a">Первая строка.</param>
    /// <param name="b">Вторая строка.</param>
    /// <returns>Лексикографически наименьшая из самых длинных общих подстрок
    /// (пустая строка, если общих подстрок нет).</returns>
    public static string Solution(string a, string b)
    {
        if (string.IsNullOrEmpty(a) || string.IsNullOrEmpty(b))
            return string.Empty;

        // Префиксные хеши и степени основания для обеих строк
        var (pref1A, pref2A) = BuildPrefixHashes(a);
        var (pref1B, pref2B) = BuildPrefixHashes(b);
        int maxPow = Math.Max(a.Length, b.Length) + 1;
        var (pow1, pow2) = BuildPowers(maxPow);

        // Шаг 1. Бинарный поиск максимальной длины общей подстроки
        int lo = 0, hi = Math.Min(a.Length, b.Length);
        int maxLen = 0;
        while (lo <= hi)
        {
            int mid = (lo + hi) / 2;
            if (mid == 0 || HasCommonOfLength(mid, a, b, pref1A, pref2A, pref1B, pref2B, pow1, pow2) != null)
            {
                maxLen = mid;
                lo = mid + 1;
            }
            else
            {
                hi = mid - 1;
            }
        }

        if (maxLen == 0)
            return string.Empty;

        // Шаг 2. Среди всех общих подстрок длины maxLen выбираем лексикографически наименьшую
        return FindLexSmallest(maxLen, a, b, pref1A, pref2A, pref1B, pref2B, pow1, pow2);
    }

    /// <summary>
    /// Проверяет, существует ли общая подстрока длины length.
    /// Возвращает любую такую подстроку или null, если её нет.
    /// </summary>
    private static string? HasCommonOfLength(int length, string a, string b,
        long[] pref1A, long[] pref2A, long[] pref1B, long[] pref2B,
        long[] pow1, long[] pow2)
    {
        // Все хеши подстрок длины length из строки a
        var hashesA = new HashSet<(long, long)>();
        for (int i = 0; i + length <= a.Length; i++)
            hashesA.Add(SubHash(pref1A, pref2A, pow1, pow2, i, length));

        // Ищем совпадение среди подстрок строки b
        for (int i = 0; i + length <= b.Length; i++)
        {
            var h = SubHash(pref1B, pref2B, pow1, pow2, i, length);
            if (hashesA.Contains(h))
                return b.Substring(i, length);
        }

        return null;
    }

    /// <summary>
    /// Среди всех общих подстрок длины maxLen возвращает лексикографически наименьшую.
    /// </summary>
    private static string FindLexSmallest(int maxLen, string a, string b,
        long[] pref1A, long[] pref2A, long[] pref1B, long[] pref2B,
        long[] pow1, long[] pow2)
    {
        // Множество хешей подстрок строки b
        var hashesB = new HashSet<(long, long)>();
        for (int i = 0; i + maxLen <= b.Length; i++)
            hashesB.Add(SubHash(pref1B, pref2B, pow1, pow2, i, maxLen));

        string? best = null;
        var processed = new HashSet<(long, long)>();

        // Перебираем подстроки строки a; общие — это кандидаты на ответ
        for (int i = 0; i + maxLen <= a.Length; i++)
        {
            var h = SubHash(pref1A, pref2A, pow1, pow2, i, maxLen);
            if (!hashesB.Contains(h))
                continue;
            if (!processed.Add(h))
                continue; // такую подстроку уже рассматривали

            string candidate = a.Substring(i, maxLen);
            if (best == null || string.CompareOrdinal(candidate, best) < 0)
                best = candidate;
        }

        return best ?? string.Empty;
    }

    /// <summary>
    /// Строит префиксные хеши строки для двух модулей.
    /// </summary>
    private static (long[] pref1, long[] pref2) BuildPrefixHashes(string s)
    {
        long[] pref1 = new long[s.Length + 1];
        long[] pref2 = new long[s.Length + 1];
        for (int i = 0; i < s.Length; i++)
        {
            pref1[i + 1] = (pref1[i] * Base1 + s[i]) % Mod1;
            pref2[i + 1] = (pref2[i] * Base2 + s[i]) % Mod2;
        }
        return (pref1, pref2);
    }

    /// <summary>
    /// Заранее считает степени оснований хеширования.
    /// </summary>
    private static (long[] pow1, long[] pow2) BuildPowers(int count)
    {
        long[] pow1 = new long[count];
        long[] pow2 = new long[count];
        pow1[0] = pow2[0] = 1;
        for (int i = 1; i < count; i++)
        {
            pow1[i] = pow1[i - 1] * Base1 % Mod1;
            pow2[i] = pow2[i - 1] * Base2 % Mod2;
        }
        return (pow1, pow2);
    }

    /// <summary>
    /// Возвращает пару хешей подстроки [start, start + length).
    /// </summary>
    private static (long, long) SubHash(long[] pref1, long[] pref2, long[] pow1, long[] pow2,
        int start, int length)
    {
        long h1 = (pref1[start + length] - pref1[start] * pow1[length] % Mod1 + Mod1 * Mod1) % Mod1;
        long h2 = (pref2[start + length] - pref2[start] * pow2[length] % Mod2 + Mod2 * Mod2) % Mod2;
        return (h1, h2);
    }
}
