using System;
using System.Globalization;
using System.IO;

namespace CW3.Task29.Acmp;

/// <summary>
/// Задача 29. Метро — самодостаточный файл для отправки решения.
///
/// Целевая платформа: .NET 8. Скопировать всё содержимое файла
/// (включая using-и и namespace) в окно отправки решения.
///
/// Формат ввода (stdin):
///   N M
///   K
///   x1 y1
///   ...
///   xK yK
/// где N×M (0 &lt; N, M ≤ 1000) — размер сетки кварталов,
/// K (0 ≤ K ≤ 100) — количество кварталов с диагональю,
/// (xi, yi) — координаты этих кварталов.
///
/// Формат вывода (stdout):
///   Длина кратчайшего пути от дома Никифора (перекрёсток (0,0)) до станции метро
///   (перекрёсток (N,M)) в метрах, округлённая до целого.
///
/// Решение: dist[x, y] — кратчайший путь до перекрёстка (x, y). В узел (x, y)
/// можно прийти с запада (+100), с юга (+100) или по диагонали квартала
/// (x, y), если она есть (+100·√2).
/// </summary>
internal static class Acmp29
{
    private static void Main()
    {
        var inv = CultureInfo.InvariantCulture;
        var tokens = Console.In.ReadToEnd()
            .Split(new[] { ' ', '\t', '\r', '\n' }, StringSplitOptions.RemoveEmptyEntries);

        int idx = 0;
        int n = int.Parse(tokens[idx++], inv);
        int m = int.Parse(tokens[idx++], inv);
        int k = int.Parse(tokens[idx++], inv);

        const double Side = 100.0;
        double diagonal = Side * Math.Sqrt(2.0);

        bool[,] hasDiag = new bool[n + 1, m + 1];
        for (int i = 0; i < k; i++)
        {
            int x = int.Parse(tokens[idx++], inv);
            int y = int.Parse(tokens[idx++], inv);
            if (x >= 1 && x <= n && y >= 1 && y <= m)
                hasDiag[x, y] = true;
        }

        double[,] dist = new double[n + 1, m + 1];
        for (int x = 0; x <= n; x++)
            for (int y = 0; y <= m; y++)
            {
                if (x == 0 && y == 0)
                {
                    dist[x, y] = 0.0;
                    continue;
                }

                double best = double.MaxValue;
                if (x > 0)
                    best = Math.Min(best, dist[x - 1, y] + Side);
                if (y > 0)
                    best = Math.Min(best, dist[x, y - 1] + Side);
                if (x > 0 && y > 0 && hasDiag[x, y])
                    best = Math.Min(best, dist[x - 1, y - 1] + diagonal);

                dist[x, y] = best;
            }

        long answer = (long)Math.Round(dist[n, m], MidpointRounding.AwayFromZero);
        Console.WriteLine(answer.ToString(inv));
    }
}
