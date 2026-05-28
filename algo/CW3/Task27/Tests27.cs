using CW3.Utils;

namespace CW3.Task27;

public class Tests27
{
    private const double Eps = 1e-4;

    public static void Test()
    {
        Console.WriteLine("################ Задача 27. Передающие станции ################\n");

        // Тест 1: пример из условия
        RunTest(new long[][] {
            new long[] { 0, 0 }, new long[] { 1, 0 }, new long[] { 0, 1 },
            new long[] { 1, 1 }, new long[] { 3, 3 }
        }, 2.8284271, "Пример из условия");

        // Тест 2: две вышки
        RunTest(new long[][] { new long[] { 0, 0 }, new long[] { 3, 4 } }, 5.0, "Две вышки, расстояние 5");

        // Тест 3: две вышки в одной точке
        RunTest(new long[][] { new long[] { 0, 0 }, new long[] { 0, 0 } }, 0.0, "Совпадающие вышки");

        // Тест 4: три вышки на одной прямой
        RunTest(new long[][] {
            new long[] { 0, 0 }, new long[] { 0, 5 }, new long[] { 0, 10 }
        }, 5.0, "Вышки на одной прямой");

        // Тест 5: вершины квадрата
        RunTest(new long[][] {
            new long[] { 0, 0 }, new long[] { 0, 2 }, new long[] { 2, 0 }, new long[] { 2, 2 }
        }, 2.0, "Вершины квадрата");

        // Тест 6: цепочка с одной далёкой вышкой
        RunTest(new long[][] {
            new long[] { 0, 0 }, new long[] { 1, 0 }, new long[] { 2, 0 }, new long[] { 10, 0 }
        }, 8.0, "Одна далёкая вышка определяет радиус");

        // Тест 7: вершины большого квадрата
        RunTest(new long[][] {
            new long[] { 0, 0 }, new long[] { 3, 0 }, new long[] { 0, 3 }, new long[] { 3, 3 }
        }, 3.0, "Вершины квадрата со стороной 3");

        // Тест 8: отрицательные координаты
        RunTest(new long[][] {
            new long[] { -5, -5 }, new long[] { -5, 5 }, new long[] { 5, -5 }, new long[] { 5, 5 }
        }, 10.0, "Отрицательные координаты");

        // Тест 9: классический треугольник 6-8-10
        RunTest(new long[][] { new long[] { 0, 0 }, new long[] { 6, 8 } }, 10.0, "Расстояние 10");

        // Тест 10: плотный кластер и одна очень далёкая вышка
        RunTest(new long[][] {
            new long[] { 0, 0 }, new long[] { 1, 1 }, new long[] { 2, 2 }, new long[] { 100, 100 }
        }, 138.5929291, "Кластер и далёкая вышка");
    }

    private static void RunTest(long[][] towers, double expected, string testName)
    {
        Console.WriteLine($"Тест: {testName}");
        try
        {
            int n = towers.Length;
            var profileResult = AlgorithmProfiler.Profile("Передающие станции", () =>
                Task27.Solution(n, towers));

            double result = profileResult.Result!.FirstOrDefault();

            Console.WriteLine($"Входные данные: вышки = [{string.Join("; ", towers.Select(t => $"({t[0]},{t[1]})"))}]");
            if (Math.Abs(result - expected) < Eps)
                Console.WriteLine($"Тест пройден успешно! (ожидалось {expected:F4}, получено {result:F4})");
            else
                Console.WriteLine($"ПРОВАЛЕНО! Ожидалось: {expected:F4}, получено: {result:F4}");

            Console.WriteLine(profileResult.ToString());
            Console.WriteLine("════════════════════════════════════════\n");
        }
        catch (Exception ex)
        {
            Console.WriteLine($"ERROR: {ex.Message}");
        }
    }
}
