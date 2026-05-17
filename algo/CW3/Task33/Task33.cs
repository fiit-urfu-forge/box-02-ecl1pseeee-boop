using System.Globalization;

namespace CW3.Task33;

/// <summary>
/// Задача 33. На метро или пешком?
///
/// Нужно попасть из точки A в точку B. Можно идти пешком (в любом направлении по прямой)
/// или ехать на метро. В метро входят/выходят только на станциях, переезд между двумя
/// соединёнными станциями идёт по прямой со скоростью метро. Скорость метро всегда больше
/// скорости ходьбы. Требуется найти минимальное время и список станций маршрута.
///
/// Решение: строим граф из узлов «станции + A + B». Рёбра-метро соединяют станции по
/// списку соединений (вес = расстояние / скорость метро). Пешие рёбра — между всеми
/// парами вершин (A↔B, A/B↔каждая станция, каждая пара станций) с весом
/// расстояние / скорость ходьбы. Пешие переходы между станциями обязательны: оптимум
/// может выглядеть как A → walk → S1 → metro → S2 → walk → S3 → metro → S4 → walk → B,
/// где шаг S2 → walk → S3 не сводится к прямому пешему A↔B. Дальше — алгоритм
/// Дейкстры от A до B с восстановлением пути.
/// </summary>
public class Task33
{
    /// <summary>
    /// Метод для запуска решения на примере из условия.
    /// </summary>
    public static void ExecuteTask()
    {
        Console.WriteLine("################ Задача 33. На метро или пешком? ################\n");

        double walkSpeed = 1, metroSpeed = 100;
        double[][] stations =
        {
            new double[] { 0, 0 },
            new double[] { 1, 0 },
            new double[] { 9, 0 },
            new double[] { 9, 9 }
        };
        int[][] connections =
        {
            new[] { 1, 2 },
            new[] { 1, 3 },
            new[] { 2, 4 }
        };
        double[] a = { 10, 10 };
        double[] b = { 10, 0 };

        var (time, path) = Solution(walkSpeed, metroSpeed, stations, connections, a, b);

        Console.WriteLine(time.ToString("F7", CultureInfo.InvariantCulture));
        Console.WriteLine($"{path.Length} {string.Join(" ", path)}");
        Console.WriteLine("════════════════════════════════════════\n");
    }

    /// <summary>
    /// Основная логика решения.
    /// </summary>
    /// <param name="walkSpeed">Скорость ходьбы.</param>
    /// <param name="metroSpeed">Скорость перемещения на метро.</param>
    /// <param name="stations">Координаты станций: stations[i] = {x, y}.</param>
    /// <param name="connections">Соединения станций (пары номеров, нумерация с 1).</param>
    /// <param name="a">Координаты точки A.</param>
    /// <param name="b">Координаты точки B.</param>
    /// <returns>Минимальное время и список номеров станций маршрута (нумерация с 1).</returns>
    public static (double time, int[] path) Solution(double walkSpeed, double metroSpeed,
        double[][] stations, int[][] connections, double[] a, double[] b)
    {
        int n = stations.Length;
        int nodeA = n;       // узел A
        int nodeB = n + 1;   // узел B
        int total = n + 2;

        // Список смежности: для каждого узла — пары (сосед, вес ребра)
        var graph = new List<(int to, double weight)>[total];
        for (int i = 0; i < total; i++)
            graph[i] = new List<(int, double)>();

        void AddEdge(int u, int v, double w)
        {
            graph[u].Add((v, w));
            graph[v].Add((u, w));
        }

        // Рёбра-метро между соединёнными станциями
        foreach (var c in connections)
        {
            int u = c[0] - 1, v = c[1] - 1;
            double w = Distance(stations[u], stations[v]) / metroSpeed;
            AddEdge(u, v, w);
        }

        // Пешие рёбра: A и B с каждой станцией
        for (int i = 0; i < n; i++)
        {
            AddEdge(nodeA, i, Distance(a, stations[i]) / walkSpeed);
            AddEdge(nodeB, i, Distance(b, stations[i]) / walkSpeed);
        }

        // Пеший переход напрямую из A в B
        AddEdge(nodeA, nodeB, Distance(a, b) / walkSpeed);

        // Пешие рёбра между всеми парами станций
        for (int i = 0; i < n; i++)
            for (int j = i + 1; j < n; j++)
                AddEdge(i, j, Distance(stations[i], stations[j]) / walkSpeed);

        // Алгоритм Дейкстры от A
        double[] dist = new double[total];
        int[] prev = new int[total];
        bool[] visited = new bool[total];
        for (int i = 0; i < total; i++)
        {
            dist[i] = double.MaxValue;
            prev[i] = -1;
        }
        dist[nodeA] = 0.0;

        var pq = new PriorityQueue<int, double>();
        pq.Enqueue(nodeA, 0.0);
        while (pq.Count > 0)
        {
            int u = pq.Dequeue();
            if (visited[u]) continue;
            visited[u] = true;

            foreach (var (to, w) in graph[u])
            {
                if (dist[u] + w < dist[to])
                {
                    dist[to] = dist[u] + w;
                    prev[to] = u;
                    pq.Enqueue(to, dist[to]);
                }
            }
        }

        // Восстановление пути от A до B и выделение из него номеров станций
        var stationPath = new List<int>();
        int cur = nodeB;
        while (cur != -1)
        {
            if (cur < n)
                stationPath.Add(cur + 1); // нумерация станций с 1
            cur = prev[cur];
        }
        stationPath.Reverse();

        return (dist[nodeB], stationPath.ToArray());
    }

    /// <summary>
    /// Евклидово расстояние между двумя точками.
    /// </summary>
    private static double Distance(double[] p, double[] q)
    {
        double dx = p[0] - q[0];
        double dy = p[1] - q[1];
        return Math.Sqrt(dx * dx + dy * dy);
    }
}
