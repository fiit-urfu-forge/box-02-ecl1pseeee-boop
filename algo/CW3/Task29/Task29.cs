namespace CW3.Task29;

/// <summary>
/// Задача 29. Метро.
///
/// Город — сетка кварталов размером N×M, сторона квартала 100 метров. Никифор идёт
/// из юго-западного угла (точка (0,0)) в северо-восточный угол (точка (N,M)) и может
/// двигаться только на север, восток или северо-восток. Через некоторые кварталы
/// проложена диагональ из юго-западного в северо-восточный угол. Нужно найти длину
/// кратчайшего пути, округлённую до целых метров.
///
/// Решение: динамика по узлам сетки. dist[x][y] — кратчайший путь до перекрёстка (x,y).
/// В узел (x,y) можно прийти с запада (+100), с юга (+100) или по диагонали квартала
/// (x,y), если она есть, из узла (x-1,y-1) (+100·√2).
/// </summary>
public class Task29
{
    /// <summary>
    /// Метод для запуска решения на примере из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        Console.WriteLine("################ Задача 29. Метро ################\n");

        int n = 3, m = 2;
        int[][] diagonals =
        {
            new[] { 1, 1 },
            new[] { 3, 2 },
            new[] { 1, 2 }
        };

        Console.WriteLine($"Сетка {n}×{m}, кварталы с диагональю: (1,1), (3,2), (1,2)");
        Console.WriteLine($"Длина кратчайшего пути: {Solution(n, m, diagonals)} (ожидается 383)");
        Console.WriteLine("════════════════════════════════════════\n");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="n">Размер сетки кварталов с запада на восток.</param>
    /// <param name="m">Размер сетки кварталов с юга на север.</param>
    /// <param name="diagonalBlocks">Координаты (x, y) кварталов с диагональю.</param>
    /// <returns>Длина кратчайшего пути в метрах, округлённая до целого.</returns>
    public static long Solution(int n, int m, int[][] diagonalBlocks)
    {
        const double Side = 100.0;
        double diagonal = Side * Math.Sqrt(2.0);

        // hasDiag[x, y] — есть ли диагональ в квартале с координатами (x, y)
        bool[,] hasDiag = new bool[n + 1, m + 1];
        foreach (var block in diagonalBlocks)
        {
            int x = block[0], y = block[1];
            if (x >= 1 && x <= n && y >= 1 && y <= m)
                hasDiag[x, y] = true;
        }

        // dist[x, y] — кратчайшее расстояние от (0,0) до перекрёстка (x, y)
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
                    best = Math.Min(best, dist[x - 1, y] + Side);          // приход с запада
                if (y > 0)
                    best = Math.Min(best, dist[x, y - 1] + Side);          // приход с юга
                if (x > 0 && y > 0 && hasDiag[x, y])
                    best = Math.Min(best, dist[x - 1, y - 1] + diagonal);  // приход по диагонали

                dist[x, y] = best;
            }

        return (long)Math.Round(dist[n, m], MidpointRounding.AwayFromZero);
    }
}
