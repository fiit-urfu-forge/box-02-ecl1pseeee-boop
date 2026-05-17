namespace CW3.Task23;

/// <summary>
/// Задача 23. Железные дороги.
///
/// В стране n городов, между каждой парой городов есть дорога типа R или B. Двигаться
/// можно только от города с меньшим номером к городу с большим. Карта оптимальна,
/// если не существует пары городов (A, B), таких что из A в B можно добраться
/// и только по дорогам типа R, и только по дорогам типа B.
///
/// Решение: для графа из дорог типа R и для графа из дорог типа B считаем достижимость.
/// Для каждого города i множество достижимых из него городов храним битовым вектором.
/// Города обрабатываются справа налево: reach[i] — объединение {j} и reach[j] по всем
/// рёбрам нужного типа. Если для какого-то i пересечение reachR[i] и reachB[i] непусто —
/// карта не оптимальна.
/// </summary>
public class Task23
{
    /// <summary>
    /// Метод для запуска решения на примерах из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        Console.WriteLine("Задача 23. Железные дороги.");
        Console.WriteLine($"Пример 1 -> {Solution(3, new[] { "RB", "R" })}");
        Console.WriteLine($"Пример 2 -> {Solution(4, new[] { "BBB", "RB", "B" })}");
        Console.WriteLine($"Пример 3 -> {Solution(5, new[] { "RRRB", "BRR", "BR", "R" })}");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="n">Количество городов.</param>
    /// <param name="rows">n-1 строка карты: rows[i] описывает дороги из города i
    /// в города i+1, i+2, ..., n-1 (нумерация городов с нуля).</param>
    /// <returns>"YES", если карта оптимальна, иначе "NO".</returns>
    public static string Solution(int n, string[] rows)
    {
        int words = (n + 63) / 64;

        // reachR[i], reachB[i] — битовые множества городов, достижимых из города i
        ulong[][] reachR = new ulong[n][];
        ulong[][] reachB = new ulong[n][];
        for (int i = 0; i < n; i++)
        {
            reachR[i] = new ulong[words];
            reachB[i] = new ulong[words];
        }

        // Идём от последнего города к первому, чтобы reach[j] уже был готов к моменту обработки i
        for (int i = n - 2; i >= 0; i--)
        {
            string row = rows[i];
            for (int k = 0; k < row.Length; k++)
            {
                int j = i + 1 + k; // сосед — город j, ребро типа row[k]
                if (row[k] == 'R')
                {
                    SetBit(reachR[i], j);
                    OrInto(reachR[i], reachR[j]);
                }
                else // 'B'
                {
                    SetBit(reachB[i], j);
                    OrInto(reachB[i], reachB[j]);
                }
            }
        }

        // Если для какого-то города пересечение достижимостей по R и по B непусто —
        // карта не оптимальна
        for (int i = 0; i < n; i++)
            if (HasCommonBit(reachR[i], reachB[i]))
                return "NO";

        return "YES";
    }

    /// <summary>Устанавливает бит с номером pos в битовом векторе.</summary>
    private static void SetBit(ulong[] set, int pos)
    {
        set[pos >> 6] |= 1UL << (pos & 63);
    }

    /// <summary>Объединяет в target все биты из source (target |= source).</summary>
    private static void OrInto(ulong[] target, ulong[] source)
    {
        for (int w = 0; w < target.Length; w++)
            target[w] |= source[w];
    }

    /// <summary>Проверяет, есть ли хотя бы один общий установленный бит у двух векторов.</summary>
    private static bool HasCommonBit(ulong[] a, ulong[] b)
    {
        for (int w = 0; w < a.Length; w++)
            if ((a[w] & b[w]) != 0)
                return true;
        return false;
    }
}
