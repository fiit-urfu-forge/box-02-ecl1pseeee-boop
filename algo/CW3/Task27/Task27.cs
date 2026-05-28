namespace CW3.Task27;

/// <summary>
/// Задача 27. Передающие станции.
///
/// На N вышках нужно установить однотипные приёмо-передатчики. Чем больше мощность
/// передатчика, тем больше радиус его действия. Две вышки могут связаться напрямую,
/// если расстояние между ними не больше радиуса. Нужно найти минимальный радиус,
/// при котором все вышки связаны (возможно, через ретрансляцию).
///
/// Решение: связность всех вышек при заданном радиусе r означает, что граф рёбер
/// длины &lt;= r связен. Минимальный такой r — это наибольшее ребро минимального
/// остовного дерева (минимаксное остовное дерево). Строим MST алгоритмом Прима за
/// O(N^2) и возвращаем максимальное использованное ребро.
/// </summary>
public class Task27
{
    /// <summary>
    /// Метод для запуска решения на примере из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        long[][] towers =
        {
            new long[] { 0, 0 },
            new long[] { 1, 0 },
            new long[] { 0, 1 },
            new long[] { 1, 1 },
            new long[] { 3, 3 }
        };

        Console.WriteLine("Задача 27. Передающие станции.");
        Console.WriteLine($"Результат: {Solution(towers.Length, towers):F4}");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="n">Количество вышек.</param>
    /// <param name="coords">Координаты вышек: coords[i] = {x, y}.</param>
    /// <returns>Минимальный требуемый радиус.</returns>
    public static double Solution(int n, long[][] coords)
    {
        if (n <= 1)
            return 0.0;

        // Алгоритм Прима: minDist[i] — минимальное расстояние от вышки i до дерева
        bool[] inTree = new bool[n];
        double[] minDist = new double[n];
        for (int i = 0; i < n; i++)
            minDist[i] = double.MaxValue;

        minDist[0] = 0.0;
        double answer = 0.0;

        for (int step = 0; step < n; step++)
        {
            // Выбираем ещё не добавленную вышку с минимальным расстоянием до дерева
            int v = -1;
            for (int i = 0; i < n; i++)
                if (!inTree[i] && (v == -1 || minDist[i] < minDist[v]))
                    v = i;

            inTree[v] = true;
            // Ребро, которым вышку v присоединили к дереву, может оказаться
            // самым большим в остовном дереве
            answer = Math.Max(answer, minDist[v]);

            // Обновляем расстояния до оставшихся вышек
            for (int u = 0; u < n; u++)
            {
                if (inTree[u]) continue;
                double d = Distance(coords[v], coords[u]);
                if (d < minDist[u])
                    minDist[u] = d;
            }
        }

        return answer;
    }

    /// <summary>
    /// Евклидово расстояние между двумя точками.
    /// </summary>
    private static double Distance(long[] a, long[] b)
    {
        double dx = a[0] - b[0];
        double dy = a[1] - b[1];
        return Math.Sqrt(dx * dx + dy * dy);
    }
}
